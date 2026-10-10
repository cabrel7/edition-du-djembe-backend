<?php
// app/Http/Controllers/SubmissionController.php

namespace App\Http\Controllers;

use App\Mail\SubmissionReceived;
use App\Mail\SubmissionReply;
use App\Models\Submission;
use App\Support\UploadConstraints;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubmissionController extends Controller
{
    /**
     * Soumettre un nouveau document (public)
     * POST /api/submissions
     */
    public function store(Request $request)
    {
        Log::info('Nouvelle soumission reçue');
        
        // Validation des données
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:book,memoir,article',
            'first_name' => 'required|string|max:50|min:2',
            'last_name' => 'required|string|max:50|min:2',
            'email' => 'required|email|max:100',
            'phone' => 'nullable|string|max:20',
            'title' => 'required|string|max:200|min:3',
            // 'category' => 'nullable|string|max:100',
            'summary' => 'required|string|max:2000|min:100',
            'file' => 'required|file|mimes:pdf|max:20480', // 20MB max, PDF uniquement
            'rgpd_consent' => 'required|accepted'
        ], [
            'rgpd_consent.accepted' => 'Vous devez accepter les conditions RGPD',
            'file.mimes' => 'Le fichier doit être au format PDF',
            'file.max' => 'Le fichier ne doit pas dépasser 20 Mo'
        ]);

        if ($validator->fails()) {
            Log::warning('Validation échouée', ['errors' => $validator->errors()->all()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Gestion du fichier
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $originalName = basename($file->getClientOriginalName());

                // Nom unique, extension forcée côté serveur (jamais l'extension client)
                $fileName = Str::random(20) . '.' . UploadConstraints::PDF_MIMES;

                // Stocker le fichier HORS web root (storage/app/submissions)
                $stored = Storage::disk('local')->putFileAs(UploadConstraints::SUBMISSIONS_DIR, $file, $fileName);

                if ($stored === false) {
                    throw new \Exception('Erreur lors du stockage du fichier');
                }
                
                // Créer la soumission
                $submission = Submission::create([
                    'type' => $request->type,
                    'first_name' => strip_tags($request->first_name),
                    'last_name' => strip_tags($request->last_name),
                    'email' => $request->email,
                    'phone' => $request->phone ? strip_tags($request->phone) : null,
                    'title' => strip_tags($request->title),
                    // 'category' => $request->category ? strip_tags($request->category) : null,
                    'summary' => strip_tags($request->summary),
                    'file_path' => $fileName,
                    'file_name' => $originalName,
                    
                    'rgpd_consent' => true,
                    'status' => 'pending',
                    'admin_notes' => null
                ]);

                Log::info('Soumission créée avec succès', [
                    'id' => $submission->id,
                    'author' => $submission->author
                ]);

                // Accusé de réception par email (non bloquant : échec SMTP ≠ échec soumission)
                try {
                    Mail::to($submission->email)->send(new SubmissionReceived($submission));
                } catch (\Exception $mailException) {
                    Log::warning('Échec envoi accusé de réception', [
                        'submission_id' => $submission->id,
                        'error' => $mailException->getMessage(),
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Votre soumission a été envoyée avec succès !',
                    'data' => [
                        'id' => $submission->id,
                        'author' => $submission->author,
                        'title' => $submission->title,
                        'submission_date' => $submission->created_at->format('d/m/Y H:i')
                    ]
                ], 201);

            } else {
                throw new \Exception('Aucun fichier téléchargé');
            }

        } catch (\Exception $e) {
            Log::error('Erreur lors de la soumission', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi de votre soumission.'
            ], 500);
        }
    }

    /**
     * Lister toutes les soumissions (admin)
     * GET /api/admin/submissions
     */
    public function index(Request $request)
    {
        // Vérifier l'authentification et les droits admin
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 401);
        }

        try {
            // Récupérer les paramètres de filtrage (per_page borné : DoS ?per_page=999999)
            $perPage = min(max((int) $request->get('per_page', 20), 1), 50);
            $page = $request->get('page', 1);
            $type = $request->get('type');
            $status = $request->get('status');
            $search = $request->get('search');

            // Construire la requête
            $query = Submission::query();

            // Appliquer les filtres
            if ($type && in_array($type, ['book', 'memoir', 'article'])) {
                $query->where('type', $type);
            }

            if ($status && in_array($status, ['pending', 'reviewing', 'accepted', 'rejected', 'archived'])) {
                $query->where('status', $status);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                      ->orWhere('last_name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('title', 'LIKE', "%{$search}%")
                      ->orWhere('summary', 'LIKE', "%{$search}%");
                });
            }

            // Trier par date (plus récent d'abord)
            $query->orderBy('created_at', 'desc');

            // Pagination
            $submissions = $query->paginate($perPage, ['*'], 'page', $page);

            // Statistiques
            $stats = [
                'total' => Submission::count(),
                'pending' => Submission::pending()->count(),
                'reviewing' => Submission::reviewing()->count(),
                'accepted' => Submission::accepted()->count(),
                'rejected' => Submission::rejected()->count(),
                'archived' => Submission::archived()->count(),
                'books' => Submission::books()->count(),
                'memoirs' => Submission::memoirs()->count(),
                'articles' => Submission::articles()->count()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'submissions' => $submissions->items(),
                    'pagination' => [
                        'total' => $submissions->total(),
                        'per_page' => $submissions->perPage(),
                        'current_page' => $submissions->currentPage(),
                        'last_page' => $submissions->lastPage(),
                        'from' => $submissions->firstItem(),
                        'to' => $submissions->lastItem()
                    ],
                    'stats' => $stats
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des soumissions', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données'
            ], 500);
        }
    }

    /**
     * Afficher une soumission spécifique (admin)
     * GET /api/admin/submissions/{id}
     */
    public function show($id)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 401);
        }

        try {
            $submission = Submission::find($id);

            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soumission non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'submission' => $submission
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de la soumission', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la soumission'
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut d'une soumission (admin)
     * PUT /api/admin/submissions/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,reviewing,accepted,rejected,archived',
            'admin_notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $submission = Submission::find($id);

            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soumission non trouvée'
                ], 404);
            }

            $oldStatus = $submission->status;
            
            // Mettre à jour la soumission
            $submission->status = $request->status;
            $submission->admin_notes = $request->admin_notes;
            $submission->save();

            Log::info('Statut de soumission mis à jour', [
                'id' => $submission->id,
                'old_status' => $oldStatus,
                'new_status' => $submission->status,
                'admin' => $user->email
            ]);

            // Optionnel : Envoyer un email à l'auteur si le statut change
            // if (in_array($request->status, ['accepted', 'rejected'])) {
            //     $this->sendStatusUpdateEmail($submission);
            // }

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'data' => [
                    'submission' => $submission
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour du statut', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut'
            ], 500);
        }
    }

    /**
     * Télécharger le fichier d'une soumission (admin)
     * GET /api/admin/submissions/{id}/download
     */
    public function download($id)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 401);
        }

        try {
            $submission = Submission::find($id);

            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soumission non trouvée'
                ], 404);
            }

            $storagePath = storage_path('app/' . UploadConstraints::SUBMISSIONS_DIR . '/' . $submission->file_path);
            $legacyPath = public_path(UploadConstraints::SUBMISSIONS_DIR . '/' . $submission->file_path);

            $filePath = file_exists($storagePath) ? $storagePath : (file_exists($legacyPath) ? $legacyPath : null);

            if ($filePath === null) {
                Log::error('Fichier non trouvé', [
                    'submission_id' => $id,
                    'file_path' => $submission->file_path
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Fichier non trouvé'
                ], 404);
            }

            // Enregistrer le téléchargement
            Log::info('Fichier téléchargé', [
                'submission_id' => $id,
                'admin' => $user->email,
                'file_name' => $submission->file_name
            ]);

            return response()->download($filePath, $submission->file_name, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $submission->file_name . '"'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors du téléchargement', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement du fichier'
            ], 500);
        }
    }

    /**
     * Supprimer une soumission (admin)
     * DELETE /api/admin/submissions/{id}
     */
    public function destroy($id)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 401);
        }

        try {
            $submission = Submission::find($id);

            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soumission non trouvée'
                ], 404);
            }

            // Supprimer le fichier physique (nouveau chemin storage/app/submissions, fallback legacy public/submissions)
            $candidates = [
                storage_path('app/' . UploadConstraints::SUBMISSIONS_DIR . '/' . $submission->file_path),
                public_path(UploadConstraints::SUBMISSIONS_DIR . '/' . $submission->file_path),
            ];

            foreach ($candidates as $filePath) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                    break;
                }
            }

            // Supprimer l'enregistrement de la base de données
            $submission->delete();

            Log::info('Soumission supprimée', [
                'id' => $id,
                'admin' => $user->email,
                'author' => $submission->author
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Soumission supprimée avec succès'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la soumission'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques (admin)
     * GET /api/admin/submissions/statistics
     */
    public function statistics()
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 401);
        }

        try {
            // Statistiques globales
            $total = Submission::count();
            $pending = Submission::pending()->count();
            $reviewing = Submission::reviewing()->count();
            $accepted = Submission::accepted()->count();
            $rejected = Submission::rejected()->count();
            $archived = Submission::archived()->count();
            
            // Par type
            $books = Submission::books()->count();
            $memoirs = Submission::memoirs()->count();
            $articles = Submission::articles()->count();

            // Soumissions des 30 derniers jours
            $recentSubmissions = Submission::where('created_at', '>=', now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get(['id', 'type', 'author', 'title', 'status', 'created_at']);

            // Évolution mensuelle (derniers 6 mois)
            $monthlyStats = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $monthStart = $month->copy()->startOfMonth();
                $monthEnd = $month->copy()->endOfMonth();
                
                $monthlyStats[] = [
                    'month' => $month->format('M Y'),
                    'total' => Submission::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                    'accepted' => Submission::whereBetween('created_at', [$monthStart, $monthEnd])
                        ->where('status', 'accepted')
                        ->count()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => [
                        'total' => $total,
                        'pending' => $pending,
                        'reviewing' => $reviewing,
                        'accepted' => $accepted,
                        'rejected' => $rejected,
                        'archived' => $archived,
                        'by_type' => [
                            'books' => $books,
                            'memoirs' => $memoirs,
                            'articles' => $articles
                        ]
                    ],
                    'monthly_trend' => $monthlyStats,
                    'recent_submissions' => $recentSubmissions
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }

    /**
     * Envoyer un email à l'auteur d'une soumission (admin)
     * POST /api/admin/submissions/{id}/send-email
     */
    public function sendEmail(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user || $user->role != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $submission = Submission::find($id);

            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soumission non trouvée'
                ], 404);
            }

            if (!$submission->email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette soumission n\'a pas d\'adresse email'
                ], 422);
            }

            Mail::to($submission->email)->send(new SubmissionReply(
                $submission,
                $request->subject,
                $request->message
            ));

            Log::info('Email envoyé à un soumissionnaire', [
                'submission_id' => $submission->id,
                'to' => $submission->email,
                'subject' => $request->subject,
                'admin' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email envoyé avec succès'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi de l\'email', [
                'submission_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'envoyer l\'email. Vérifiez la configuration SMTP.'
            ], 500);
        }
    }

    /**
     * Méthode privée pour envoyer un email de confirmation
     * (À implémenter selon votre configuration d'email)
     */
    private function sendConfirmationEmail($submission)
    {
        // Exemple d'implémentation avec Mail
        /*
        \Mail::send('emails.submission_confirmation', ['submission' => $submission], function($message) use ($submission) {
            $message->to($submission->email)
                    ->subject('Confirmation de réception de votre soumission');
        });
        */
    }

    /**
     * Méthode privée pour envoyer un email de mise à jour de statut
     * (À implémenter selon votre configuration d'email)
     */
    private function sendStatusUpdateEmail($submission)
    {
        // Implémentation similaire à sendConfirmationEmail
    }
}