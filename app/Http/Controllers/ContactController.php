<?php
// app/Http/Controllers/ContactController.php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    /**
     * Récupère tous les contacts (pour l'admin)
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Récupérer les paramètres de requête
        $status = $request->query('status');
        $search = $request->query('search');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $perPage = $request->query('per_page', 20);
        
        // Commencer la requête
        $query = Contact::select();
        
        // Appliquer les filtres
        if ($status && in_array($status, ['pending', 'read', 'replied', 'archived'])) {
            $query->where('status', $status);
        }
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('subject', 'LIKE', "%{$search}%")
                  ->orWhere('message', 'LIKE', "%{$search}%")
                  ->orWhere('company', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }
        
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        
        // Trier par date de création (plus récent d'abord)
        $query->orderBy('created_at', 'desc');
        
        // Pagination
        $contacts = $query->paginate($perPage);
        
        return response()->json([
            'contacts' => $contacts->items(),
            'pagination' => [
                'total' => $contacts->total(),
                'per_page' => $contacts->perPage(),
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'has_more' => $contacts->hasMorePages()
            ]
        ], 200);
    }

    /**
     * Récupère un contact spécifique
     */
    public function show($id)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $contact = Contact::find($id);
        
        if (!$contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }
        
        return response()->json(['contact' => $contact], 200);
    }

    /**
     * Crée un nouveau contact (depuis le formulaire public)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:100',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:2000'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $contact = Contact::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'company' => $request->company,
            'subject' => $request->subject,
            'message' => $request->message,
            'status' => 'pending'
        ]);
        
        // Ici vous pourriez ajouter l'envoi d'email de notification
        
        return response()->json([
            'message' => 'Contact message sent successfully',
            'contact' => $contact
        ], 201);
    }

    /**
     * Met à jour le statut d'un contact
     */
    public function updateStatus(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,read,replied,archived'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $contact = Contact::find($id);
        
        if (!$contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }
        
        $contact->status = $request->status;
        $contact->save();
        
        return response()->json([
            'message' => 'Contact status updated successfully',
            'contact' => $contact
        ], 200);
    }

    /**
     * Ajoute une réponse à un contact
     */
    

    /**
     * Supprime un contact
     */
    public function destroy($id)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $contact = Contact::find($id);
        
        if (!$contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }
        
        $contact->delete();
        
        return response()->json([
            'message' => 'Contact deleted successfully'
        ], 200);
    }

    /**
     * Statistiques des contacts
     */
    public function statistics()
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $total = Contact::count();
        $pending = Contact::pending()->count();
        $read = Contact::read()->count();
        $replied = Contact::replied()->count();
        $archived = Contact::archived()->count();
        
        // Contacts des 7 derniers jours
        $recentContacts = Contact::where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();
        
        return response()->json([
            'statistics' => [
                'total' => $total,
                'pending' => $pending,
                'read' => $read,
                'replied' => $replied,
                'archived' => $archived
            ],
            'recent_contacts' => $recentContacts
        ], 200);
    }
}
