<?php

namespace App\Http\Controllers;

use App\Models\MailAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class MailAccountController extends Controller
{
    public function index()
    {
        return Auth::user()->mailAccounts;
    }


    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'imap_host' => 'required|string',
            'imap_port' => 'required|integer',
            'encryption' => 'required|string',
        ]);

        $mailAccount = MailAccount::create([
            'user_id' => Auth::id(),
            'email' => $request->email,
            'password' => encrypt($request->password),
            'imap_host' => $request->imap_host,
            'imap_port' => $request->imap_port,
            'encryption' => $request->encryption,
        ]);

        return response()->json($mailAccount, 201);
    }


    public function show($id)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('view', $mailAccount);

        return $mailAccount;
    }


    public function update(Request $request, $id)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('update', $mailAccount);

        $mailAccount->update($request->all());

        return response()->json($mailAccount, 200);
    }


    public function destroy($id)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('delete', $mailAccount);

        $mailAccount->delete();

        return response()->json(null, 204);
    }
}
