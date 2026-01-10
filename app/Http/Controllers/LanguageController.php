<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    /**
     * Switch the application language
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $lang
     * @return \Illuminate\Http\RedirectResponse
     */
    public function switchLanguage(Request $request, $lang)
    {
        // Validate that the language exists in our constants
        $availableLanguages = array_keys(config('constants.langs'));
        
        if (!in_array($lang, $availableLanguages)) {
            $lang = config('app.locale'); // Default to 'bn'
        }

        // Set locale in session
        if ($request->session()->has('user')) {
            $user = $request->session()->get('user');
            $user['language'] = $lang;
            $request->session()->put('user', $user);
        } else {
            // If user session doesn't exist, create a temporary one
            $request->session()->put('user.language', $lang);
        }

        // Set the application locale
        App::setLocale($lang);

        // Redirect back to the previous page
        return redirect()->back();
    }
}

