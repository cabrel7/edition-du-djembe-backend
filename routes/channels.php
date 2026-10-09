<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('commentaire.{recette_id}', function ($user, $recette_id) {

    return true;
});

Broadcast::channel('like_commentaire.{recette_id}', function ($user, $recette_id) {

    return true;
});
Broadcast::channel('dislike_commentaire.{recette_id}', function ($user, $recette_id) {

    return true;
});
Broadcast::channel('chat.{receive_id}', function ($user, $receive_id) {

    return true;
});
Broadcast::channel('chatgroup.{groupe_id}', function ($user, $groupe_id) {

    return true;
});
Broadcast::channel('actualise.{id}', function ($user, $id) {

    return true;
});

Broadcast::channel('chat', function ($user) {
    return true; // Ou logiques d'autorisation
});

Broadcast::channel('public', function ($user) {
    return true;
});

Broadcast::channel('test.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('notif.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
use App\Models\Ama_participant;

Broadcast::channel('ama-session.{id}', function ($user, $id) {
    return Ama_participant::where('user_id', $user->id)
        ->where('ama_session_id', $id)
        ->exists();
});

