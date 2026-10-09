<?php

namespace App\Models;


use App\Models\Categorie;
use COM;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable 
{
    use  HasFactory, Notifiable,HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'prenom',
        'phone',
        'country',
        'verified',
        'certified',
        'date_naiss',
        'is_premium',
        'avatar',
        'is_focusing',
        'email_verified_at',
        'email',
        'password',
        'isconnected',
        'focus',
        'followers'
        ,
        
    ];
   
    public function categories(){
    return $this->belongsToMany(Categorie::class);
}
public function formationsParticipees()
{
    return $this->belongsToMany(Formation::class, 'formation_participants', 'user_id', 'formation_id')
                ->withPivot('inscrit_le')
                ->withTimestamps();
}

public function formationsCreees()
{
    return $this->hasMany(Formation::class, 'user_id');
}
    public function post(){
        return $this->HasMany(post::class);
    }
    public function project(){
        return $this->HasMany(project::class);
    }
    public function likedPosts(){
    return $this->belongsToMany(Post::class, 'likes', 'user_id', 'post_id')->withTimestamps();
}
public function comments(){
    return $this->hasMany(Comment::class);
}
public function follower(){
    return $this->belongsToMany(follow::class, 'follows', 'user_id', 'follower_id')->withTimestamps();
}
public function followup(){
    return $this->belongsToMany(follow::class, 'follows', 'follower_id', 'user_id')->withTimestamps();
}

// public function followproject(){
//     return $this->belongsToMany(projectaudiance::class, 'projectaudiances', 'user_id', 'project_id')->withTimestamps();
// }


public function likedProjects(){
    return $this->belongsToMany(project::class, 'projectlikes', 'user_id', 'project_id')->withTimestamps();
}
// public function commentproject(){
//     return $this->hasMany(projectcomment::class);
// }
public function groupes(){
    return $this->belongsToMany(Groupe::class,'appartenir_groupes','user_id','groupe_id');
}
public function messagesnonlus(){
    return $this->hasMany(Message::class,'receive_id','id','lu',0);
}
public function notification()
{
    return $this->hasMany(notification::class);
}
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
         'focus'=>'array',
    ];
}
