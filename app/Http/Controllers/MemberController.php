<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\MemberResource;

class MemberController extends Controller
{
    public function index()
    {
        $members = Member::with('books')->paginate(10);
        return MemberResource::collection($members);
    }

    public function show(Member $member)
    {
        return new MemberResource($member->load('books'));
    }

    public function borrowBook(Request $request, Member $member)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
        ]);

        return DB::transaction(function () use ($request, $member) {
            $book = Book::where('id', $request->book_id)
                        ->lockForUpdate()
                        ->firstOrFail();

            if (!$book->is_available) {
                return response()->json([
                    'message' => 'Buku sedang dipinjam',
                ], 409);
            }

            $book->update([
                'member_id'    => $member->id,
                'is_available' => false,
            ]);

            return response()->json([
                'message' => 'Buku berhasil dipinjam',
                'data'    => $book,
            ], 200);
        });
    }

    public function returnBook(Member $member, Book $book)
    {
        if ($book->member_id !== $member->id) {
            return response()->json([
                'message' => 'Buku ini tidak sedang dipinjam oleh member ini',
            ], 409);
        }

        $book->update([
            'member_id'    => null,
            'is_available' => true,
        ]);

        return response()->json([
            'message' => 'Buku berhasil dikembalikan',
            'data'    => $book,
        ], 200);
    }
}