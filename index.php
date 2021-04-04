<?php

require_once './vendor/autoload.php';

use App\Models\User;
use App\Models\Profile;
use App\Models\Post;
use Core\Database\ORM\Collection;

## delete specific models 
// User::destroy(1, 2, 3);

## retrieve single model
// $user = User::first();
// or
// $user = User::find($id = 1);

## eager load relations 
// $firstUserWithRelations = User::with(['profile', 'posts' => function ($query) {
//     $query->whereIn('id', [7, 8]);
// }])->first();

## update exists single model
// if ($user) {
//     $user->update(['name' => 'maryam']);
//     // or
//     $user->fill(['name' => 'maryam mostafa'])->save();
// }

## delete single model
// $user->delete();



// dd(User::where('name', '=', 'mostafa')->where(function ($query) {
//     $query->whereIn('id', [1, 2, 3])->orWhere(function ($query) {
//         $query->orWhereIn('status', ['active', 'approved']);
//     });
// })->toSql());

// hasOne relation 
// $user = User::first();
// $user->profile()->create(['country', 'Cairo']);
// $user->profile()->update(['country', 'Alexandria']);

// $user = User::with('profile')->first();
// $profile = $user->profile;
// $profile->country = 'Giza';
// $profile->save();

## HasMany Relation
// $post = new Post;
// $post->body = 'Mostafa post body';
// User::first()->posts()->save($post);

// $post1 = new Post(['body' => 'post 1']);
// $post2 = new Post(['body' => 'post 2']);
// User::first()->posts()->saveMany([$post1, $post2]);

// User::first()->posts()->create([
//     'body' => 'new post'
// ]);

// User::first()->posts()->createMany([
//     ['body' => 'many posts 1'],
//     ['body' => 'many posts 2'],
// ]);

// User::first()->posts()->update(['body' => 'posts updated']);

// User::first()->posts()->whereIn('id', [10, 11, 12])->delete();

// $user = User::with('posts')->first();

// $userLastPost = User::first()->posts()->limit(1)->orderBy('id', 'DESC')->first();

// $resultsToArray = User::with('profile', 'posts')->first()->toArray();

## BelongsTo Relation

$profile = Profile::with('user')->first();

dd($profile);

exit(1);
