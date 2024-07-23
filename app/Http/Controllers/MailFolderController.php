<?php

namespace App\Http\Controllers;

use App\Models\MailAccount;
use Webklex\IMAP\Facades\Client;
use Illuminate\Http\Request;

class MailFolderController extends Controller
{
    public function index($id)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('view', $mailAccount);

        $client = $this->connectToMailAccount($mailAccount);
        $folders = $client->getFolders(false);

        $folderNames = [];
        foreach ($folders as $folder) {
            $folderNames[] = $folder->name;
        }

        return response()->json($folderNames);
    }

    public function store(Request $request, $id)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('update', $mailAccount);

        $request->validate(['folder_name' => 'required|string']);

        $client = $this->connectToMailAccount($mailAccount);
        $client->createFolder($request->folder_name);

        return response()->json(['message' => 'Folder created successfully']);
    }

    public function destroy($id, $folder_name)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('delete', $mailAccount);

        $client = $this->connectToMailAccount($mailAccount);
        $folder = $client->getFolder($folder_name);
        $folder->delete();

        return response()->json(['message' => 'Folder deleted successfully']);
    }

    private function connectToMailAccount($mailAccount)
    {
        $client = Client::make([
            'host'          => $mailAccount->imap_host,
            'port'          => $mailAccount->imap_port,
            'encryption'    => $mailAccount->encryption,
            'validate_cert' => true,
            'username'      => $mailAccount->email,
            'password'      => decrypt($mailAccount->password),
            'protocol'      => 'imap'
        ]);

        $client->connect();

        return $client;
    }
}
