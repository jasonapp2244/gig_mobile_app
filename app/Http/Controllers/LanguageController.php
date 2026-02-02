<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    public function change(Request $request)
    {
        $locale = $request->input('locale', 'en');

        if (in_array($locale, ['en', 'es', 'fr','ur'])) {
            Session::put('locale', $locale);
            App::setLocale($locale);
        }

        return back();
    }
}
