<?php

namespace App\Http\Controllers;

use App\Models\Team_members;
use App\Models\Partners;
use App\Support\UploadConstraints;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TeamPartnerController extends Controller
{
    use \App\Http\Controllers\Concerns\HandlesSafeUploads;

    private const DEFAULT_IS_ACTIVE = true;

    /**
     * is_active n'est écrit que s'il est fourni ; à la création, défaut = celui de la
     * migration (actif). Une mise à jour partielle ne réactive/désactive jamais en silence.
     */
    private function applyIsActive(array &$data, Request $request, bool $isUpdate): void
    {
        $value = $this->presentBool($request, 'is_active');

        if ($value !== null) {
            $data['is_active'] = $value;
        } elseif (!$isUpdate) {
            $data['is_active'] = self::DEFAULT_IS_ACTIVE;
        }
    }

    /**
     * Écriture en base ; en cas d'échec, supprime le fichier fraîchement uploadé (jamais l'ancien)
     * puis relance l'exception (le Handler renvoie le 500 JSON habituel).
     *
     * @template T
     * @param callable(): T $write
     * @return T
     */
    private function persistOrDiscard(callable $write): mixed
    {
        try {
            return $write();
        } catch (\Throwable $e) {
            $this->discardNewUploads();

            throw $e;
        }
    }

    // ==================== TEAM MEMBERS ====================
    
    /**
     * Récupérer tous les membres d'équipe
     */
    public function getTeamMembers(Request $request)
    {
        $query = Team_members::where('is_active', true)
            ->orderBy('display_order', 'asc')
            ->orderBy('created_at', 'desc');
        
        // Pagination si nécessaire
        if ($request->has('cursor')) {
            $query->where('id', '>', $request->cursor);
        }
        
        $teamMembers = $query->limit(10)->get();
        
        return response()->json([
            'success' => true,
            'team_members' => $teamMembers,
            'hasmoreteam' => $teamMembers->count() === 10,
            'teamnexturl' => $teamMembers->count() === 10 ? $teamMembers->last()->id : null
        ]);
    }
    
    /**
     * Récupérer les membres d'équipe récents (pour la page d'accueil)
     */
    public function getRecentTeamMembers()
    {
        $teamMembers = Team_members::where('is_active', true)
            ->orderBy('display_order', 'asc')
            ->limit(3)
            ->get();
        
        return response()->json([
            'last_team_members' => $teamMembers
        ]);
    }
    
    /**
     * Créer ou mettre à jour un membre d'équipe
     */
    public function saveTeamMember(Request $request, $id = null)
    {
        $this->normalizeBoolInput($request, 'is_active');
        $this->beginUploads();

        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'role' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'bio' => 'nullable|string',
            'photo' => 'nullable|image|mimes:' . UploadConstraints::PARTNER_IMAGE_MIMES . '|max:' . UploadConstraints::MAX_IMAGE_KB,
            'display_order' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
            'social_links' => 'nullable|array'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = $request->except('photo', 'social_links', 'is_active');
        $this->applyIsActive($data, $request, (bool) $id);
        // Tableau PHP : le cast du modèle encode (pas de json_encode = pas de double encodage).
        if ($request->exists('social_links')) {
            $data['social_links'] = $request->input('social_links');
        }
        
        if ($id) {
            // Mise à jour
            $teamMember = Team_members::findOrFail($id);
            $oldPhoto = null;

            // Gérer l'upload de la photo (l'ancienne est supprimée APRÈS la mise à jour en base)
            if ($request->hasFile('photo')) {
                $oldPhoto = $teamMember->photo;
                $data['photo'] = $this->storeUpload($request->file('photo'), 'team', UploadConstraints::PARTNER_IMAGE_MIMES)['filename'];
            }

            $this->persistOrDiscard(fn () => $teamMember->update($data));
            $this->deleteStoredFile($oldPhoto, 'team');
            
            return response()->json([
                'success' => true,
                'message' => 'Membre mis à jour avec succès',
                'team_member' => $teamMember
            ]);
        } else {
            // Création
            // Gérer l'upload de la photo
            if ($request->hasFile('photo')) {
                $data['photo'] = $this->storeUpload($request->file('photo'), 'team', UploadConstraints::PARTNER_IMAGE_MIMES)['filename'];
            }

            $teamMember = $this->persistOrDiscard(fn () => Team_members::create($data));
            
            return response()->json([
                'success' => true,
                'message' => 'Membre créé avec succès',
                'team_member' => $teamMember
            ]);
        }
    }
    
    /**
     * Supprimer un membre d'équipe
     */
    public function deleteTeamMember($id)
    {
        $teamMember = Team_members::findOrFail($id);
        
        $teamMember->delete();
        $this->deleteStoredFile($teamMember->photo, 'team');
        
        return response()->json([
            'success' => true,
            'message' => 'Membre supprimé avec succès'
        ]);
    }
    
    // ==================== PARTNERS ====================
    
    /**
     * Récupérer tous les partenaires
     */
    public function getPartners(Request $request)
    {
        $query = Partners::where('is_active', true)
            ->orderBy('display_order', 'asc')
            ->orderBy('created_at', 'desc');
        
        // Pagination si nécessaire
        if ($request->has('cursor')) {
            $query->where('id', '>', $request->cursor);
        }
        
        $partners = $query->limit(10)->get();
        
        return response()->json([
            'success' => true,
            'partners' => $partners,
            'hasmorepartner' => $partners->count() === 10,
            'partnernexturl' => $partners->count() === 10 ? $partners->last()->id : null
        ]);
    }
    
    /**
     * Récupérer les partenaires récents (pour la page d'accueil)
     */
    public function getRecentPartners()
    {
        $partners = Partners::where('is_active', true)
            ->orderBy('display_order', 'asc')
            ->limit(6)
            ->get();
        
        return response()->json([
            'last_partners' => $partners
        ]);
    }
    
    /**
     * Créer ou mettre à jour un partenaire
     */
    public function savePartner(Request $request, $id = null)
    {
        $this->normalizeBoolInput($request, 'is_active');
        $this->beginUploads();

        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:' . UploadConstraints::PARTNER_IMAGE_MIMES . '|max:' . UploadConstraints::MAX_IMAGE_KB,
            'display_order' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = $request->except('image', 'is_active');
        $this->applyIsActive($data, $request, (bool) $id);
        
        if ($id) {
            // Mise à jour
            $partner = Partners::findOrFail($id);
            $oldImage = null;

            // Gérer l'upload du logo (l'ancien est supprimé APRÈS la mise à jour en base)
            if ($request->hasFile('image')) {
                $oldImage = $partner->image;
                $data['image'] = $this->storeUpload($request->file('image'), 'partners', UploadConstraints::PARTNER_IMAGE_MIMES)['filename'];
            }

            $this->persistOrDiscard(fn () => $partner->update($data));
            $this->deleteStoredFile($oldImage, 'partners');
            
            return response()->json([
                'success' => true,
                'message' => 'Partenaire mis à jour avec succès',
                'partner' => $partner
            ]);
        } else {
            // Création
            // Gérer l'upload du logo
            if ($request->hasFile('image')) {
                $data['image'] = $this->storeUpload($request->file('image'), 'partners', UploadConstraints::PARTNER_IMAGE_MIMES)['filename'];
            }

            $partner = $this->persistOrDiscard(fn () => Partners::create($data));
            
            return response()->json([
                'success' => true,
                'message' => 'Partenaire créé avec succès',
                'partner' => $partner
            ]);
        }
    }
    
    /**
     * Supprimer un partenaire
     */
    public function deletePartner($id)
    {
        $partner = Partners::findOrFail($id);
        
        $partner->delete();
        $this->deleteStoredFile($partner->image, 'partners');
        
        return response()->json([
            'success' => true,
            'message' => 'Partenaire supprimé avec succès'
        ]);
    }
    
    // ==================== ADMIN ENDPOINTS ====================
    
    /**
     * Récupérer tous les membres d'équipe (admin)
     */
    public function adminGetTeamMembers()
    {
        $teamMembers = Team_members::orderBy('display_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'team_members' => $teamMembers
        ]);
    }
    
    /**
     * Récupérer tous les partenaires (admin)
     */
    public function adminGetPartners()
    {
        $partners = Partners::orderBy('display_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'partners' => $partners
        ]);
    }
}
