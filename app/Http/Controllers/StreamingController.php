<?php

namespace App\Http\Controllers;

use App\Events\StreamAnswer;
use App\Events\StreamOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StreamingController extends Controller
{

    public function index()
    {
        return Inertia::render('Broadcast');
    }

    public function consumer(Request $request, $streamId)
    {
        return Inertia::render('Stream', ['streamId' => $streamId]);
    }

    public function makeStreamOffer(Request $request)
    {
        $data['broadcaster'] = $request->broadcaster;
        $data['receiver'] = $request->receiver;
        $data['offer'] = $request->offer;

        event(new StreamOffer($data));
    }

    public function makeStreamAnswer(Request $request)
    {
        $data['broadcaster'] = $request->broadcaster;
        $data['answer'] = $request->answer;
        event(new StreamAnswer($data));
    }
}