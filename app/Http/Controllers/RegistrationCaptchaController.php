<?php

namespace App\Http\Controllers;

use App\Domain\Auth\RegistrationCaptcha;
use Illuminate\Http\Request;

class RegistrationCaptchaController extends Controller
{
    public function issue(Request $request, RegistrationCaptcha $captcha)
    {
        $data = $request->validate(['mode' => 'required|in:visual,accessible']);

        return response()->json($captcha->issue($request, $data['mode']))->header('Cache-Control', 'no-store, private');
    }

    public function verify(Request $request, RegistrationCaptcha $captcha)
    {
        $data = $request->validate(['challenge_id' => 'required|string|size:40|alpha_num:ascii', 'captcha_answer' => 'required|string|regex:/^[0-9]{1,4}$/D']);

        return response()->json($captcha->verify($request, $data['challenge_id'], $data['captcha_answer']))->header('Cache-Control', 'no-store, private');
    }
}
