<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;

class LoginController extends Controller
{


    public function __construct()
    {
        $this->middleware('guest:admin', ['except' => 'logout']);
    }

    public function login()
    {
        return view('admin-views.auth.login');
    }

    public function submit(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        if (auth('admin')->attempt(['email' => $request->email, 'password' => $request->password], $request->remember)) {
            return redirect()->route('admin.dashboard');
            // return view('admin-views.dashboard');
        }

        return redirect()->back()->withInput($request->only('email', 'remember'))
            ->withErrors(['Credentials does not match.']);
    }

    public function logout(Request $request)
    {
        auth()->guard('admin')->logout();
        return redirect()->route('admin.auth.login');
    }

    public function sendmail(Request $request)
    {
        // auth()->guard('admin')->logout();
        // return "I am very genius !!! ";
        // return redirect()->route('web.contact-us');

        $to_name = 'RECEIVER_NAME';
        $to_email = 'RECEIVER_EMAIL_ADDRESS';
        $data = array('name'=>"first mail !@!", 'body' => "This is my first email test. Please help me with suucess and confidence.");
        // $data = array('name'=>"Ogbonna Vitalis(sender_name)", "body" => "A test mail");
        // dd($data);

        Mail::send('emails.mail', $data, function($message) use ($to_name, $to_email) 
        {

            $message->to($to_email, $to_name)->subject('Laravel Test Mail');
            $message->from('noreply@yanoqueen.com','Test Mail');
        });


    }
}
