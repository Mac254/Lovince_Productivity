<?php

namespace App\Modules\Books\Http\Controllers;

use App\Modules\Books\Http\Resources\BookResource;
use App\Modules\Books\Models\Book;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\WebpImage;
use App\Support\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A book's cover or a podcast's picture, stored privately and served only to its owner. */
class BookImageController extends Controller
{
    public function store(Request $request, Book $book): BookResource
    {
        Gate::authorize('update', $book);
        $request->validate([
            'image' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.Contract::AVATAR_MAX_KB,
                'dimensions:min_width=32,min_height=32,max_width=4096,max_height=4096',
            ],
        ]);

        $path = WebpImage::store($request->file('image'), 'book-images', $book->user_id.'-'.Str::random(32), Book::IMAGE_DISK, 512, 'image');
        $previous = $book->image_path;
        $book->forceFill(['image_path' => $path])->save();
        if ($previous) {
            Storage::disk(Book::IMAGE_DISK)->delete($previous);
        }

        return BookResource::make($book->load(Book::RELATIONS));
    }

    public function show(Book $book): StreamedResponse
    {
        Gate::authorize('view', $book);
        abort_unless($book->image_path && Storage::disk(Book::IMAGE_DISK)->exists($book->image_path), 404);

        return Storage::disk(Book::IMAGE_DISK)->response($book->image_path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function destroy(Book $book): BookResource
    {
        Gate::authorize('update', $book);
        if ($book->image_path) {
            Storage::disk(Book::IMAGE_DISK)->delete($book->image_path);
            $book->forceFill(['image_path' => null])->save();
        }

        return BookResource::make($book->load(Book::RELATIONS));
    }
}
