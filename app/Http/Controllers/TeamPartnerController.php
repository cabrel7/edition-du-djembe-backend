<?php

namespace App\Http\Controllers;

use App\Models\Team_members;
use App\Models\Partners;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TeamPartnerController extends Controller
{
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
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'role' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'bio' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'display_order' => 'nullable|integer',
            // 'is_active' => 'nullable|boolean',
            'social_links' => 'nullable|array'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = $request->except('photo', 'social_links','is_active');
        $data['is_active']=$request->is_active?1:0;
        // Gérer les liens sociaux (stockés en JSON)
        if ($request->has('social_links')) {
            $data['social_links'] = json_encode($request->social_links);
        }
        
        if ($id) {
            // Mise à jour
            $teamMember = Team_members::findOrFail($id);
            
            // Gérer l'upload de la photo
            if ($request->hasFile('photo')) {
                // Supprimer l'ancienne photo si elle existe
                // if ($teamMember->photo && Storage::exists('team/' . $teamMember->photo)) {
                //     Storage::delete('team/' . $teamMember->photo);
                // }
                
                $file = $request->file('photo');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                 $file->move(public_path('/team'), $filename);
                $data['photo'] = $filename;
            }
            
            $teamMember->update($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Membre mis à jour avec succès',
                'team_member' => $teamMember
            ]);
        } else {
            // Création
            // Gérer l'upload de la photo
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                 $file->move(public_path('/team'), $filename);
                $data['photo'] = $filename;
            }
            
            $teamMember = Team_members::create($data);
            
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
        
        // Supprimer la photo si elle existe
        // if ($teamMember->photo && Storage::exists('team/' . $teamMember->photo)) {
        //     Storage::delete('team/' . $teamMember->photo);
        // }
        
        $teamMember->delete();
        
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
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg|max:2048',
            'display_order' => 'nullable|integer',
            // 'is_active' => 'nullable|boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = $request->except('image','is_active');
        $data['is_active']=$request->is_active?1:0;
        
        if ($id) {
            // Mise à jour
            $partner = Partners::findOrFail($id);
            
            // Gérer l'upload du logo
            if ($request->hasFile('image')) {
                // Supprimer l'ancien logo si il existe
                // if ($partner->image && Storage::exists('partners/' . $partner->image)) {
                //     Storage::delete('partners/' . $partner->image);
                // }
                
                $file = $request->file('image');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                  $file->move(public_path('/partners'), $filename);
                $data['image'] = $filename;
            }
            
            $partner->update($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Partenaire mis à jour avec succès',
                'partner' => $partner
            ]);
        } else {
            // Création
            // Gérer l'upload du logo
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('/partners'), $filename);
                $data['image'] = $filename;
            }
            
            $partner = Partners::create($data);
            
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
        
        // Supprimer le logo si il existe
        // if ($partner->image && Storage::exists('partners/' . $partner->image)) {
        //     Storage::delete('partners/' . $partner->image);
        // }
        
        $partner->delete();
        
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
