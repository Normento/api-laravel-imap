<?php

namespace App\Http\Controllers;

use HTMLPurifier;
use HTMLPurifier_Config;
use Carbon\Carbon;
use App\Models\MailAccount;
use Illuminate\Http\Request;
use Webklex\PHPIMAP\Message;
use Webklex\IMAP\Facades\Client;
use Symfony\Component\Mime\Email;
use Illuminate\Support\Facades\Mail;

class MailMessageController extends Controller
{
    public function index($id, $folder_name)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('view', $mailAccount);

        $client = $this->connectToMailAccount($mailAccount);
        $folder = $client->getFolder($folder_name);
        $messages = $folder->messages()->all()->get();

        $messageList = [];
        foreach ($messages as $message) {
            $date = Carbon::parse($message->getDate());

            $messageList[] = [
                'uid' => $message->uid,
                'id' => $message->getMessageId()[0],
                'subject' => $message->getSubject()[0],
                'from' => $message->getFrom()[0],
                'date' => $date->format('Y-m-d H:i:s'),
            ];
        }

        return response()->json($messageList);
    }

    public function show($id, $folder_name, $message_uid)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('view', $mailAccount);

        $client = $this->connectToMailAccount($mailAccount);
        $folder = $client->getFolder($folder_name);
        $message = $folder->messages()->getMessageByUid((int) $message_uid);

        $date = Carbon::parse($message->getDate());

       // $attributes = $message->getAttributes();

        $attachments = [];
        foreach ($message->getAttachments() as $index => $attachment) {
            $attachments[] = [
                'name' => $attachment->name,
                'size' => $attachment->size,
                'content' => base64_encode($attachment->content),
            ];
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $purifier = new HTMLPurifier($config);

        $htmlBody = $message->getHTMLBody();
        $cleanHtmlBody = $purifier->purify($htmlBody);
        $subject = $this->decodeMimeStr($message->getSubject()[0]);
        
        $cleanHtmlBody = htmlspecialchars_decode($cleanHtmlBody, ENT_HTML5);
        $body = $this->decodeMimeStr($cleanHtmlBody);

        //dd($attachment);


        return response()->json([
            'id' => $message->getMessageId()[0],
            'subject' => $subject,
            'from' => $message->getFrom()[0],
            'body' => $body,
            'attachments' => $attachments,
            'date' => $date->format('Y-m-d H:i:s'),
        ]);
    }

    public function destroy($id, $folder_name, $message_id)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('delete', $mailAccount);
        $client = $this->connectToMailAccount($mailAccount);

        $folder = $client->getFolder($folder_name);

        $message = $folder->messages()->getMessageByUid((int) $message_id);

        $trashFolder = $client->getFolder('Trash');

        if ($trashFolder) {
            $message->move($trashFolder->path);
            return response()->json(['message' => 'Message moved to Trash folder successfully']);
        } else {
            return response()->json(['message' => 'Trash folder not found'], 404);
        }
    }


    public function restore($id, $message_id)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('update', $mailAccount);

        $client = $this->connectToMailAccount($mailAccount);
        $trashFolder = $client->getFolder('Trash');
        $inboxFolder = $client->getFolder('INBOX');

        if ($trashFolder && $inboxFolder) {
            $message = $trashFolder->messages()->getMessageByUid((int) $message_id);
            $message->move($inboxFolder->path);

            return response()->json(['message' => 'Message restored to Inbox successfully']);
        } else {
            return response()->json(['message' => 'Trash or Inbox folder not found'], 404);
        }
    }


    public function move(Request $request, $id, $folder_name, $message_id)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('update', $mailAccount);

        $request->validate(['target_folder' => 'required|string']);

        $client = $this->connectToMailAccount($mailAccount);
        $folder = $client->getFolder($folder_name);
        $message = $folder->messages()->getMessageByUid($message_id);
        $message->move($request->target_folder);

        return response()->json(['message' => 'Message moved successfully']);
    }

    public function reply(Request $request, $id, $folder_name, $message_id)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('update', $mailAccount);

        $request->validate([
            'body' => 'required|string',
            'to' => 'required|email',
        ]);

        $client = $this->connectToMailAccount($mailAccount);
        $folder = $client->getFolder($folder_name);
        $message = $folder->messages()->getMessageByUid($message_id);
        $reply = $message->reply();
        $reply->setBody($request->body);
        $reply->setTo($request->to);
        $reply->send();

        return response()->json(['message' => 'Reply sent successfully']);
    }

    public function send(Request $request, $id)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('update', $mailAccount);

       $request->validate([
            'body' => 'required|string',
            'to' => 'required|email',
            'subject' => 'required|string',
        ]);

        Mail::raw($request->body, function ($message) use ($request) {
            $message->to($request->to)
                ->subject($request->subject);
        });
        $client = $this->connectToMailAccount($mailAccount);
        $folder = $client->getFolder('Sent');

        $rawMessage = "To: {$request->to}\r\nSubject: {$request->subject}\r\n\r\n{$request->body}";

        $folder->appendMessage($rawMessage);

       

        return response()->json(['message' => 'Email sent successfully']);
    }


    public function downloadAttachment($id, $message_uid, $attachment_id)
    {
        $mailAccount = MailAccount::findOrFail($id);
        $this->authorize('view', $mailAccount);

        $client = $this->connectToMailAccount($mailAccount);
        $folder = $client->getFolder('INBOX'); 
        $message = $folder->messages()->getMessageByUid((int) $message_uid);

        $attachments = $message->getAttachments();
        if (!isset($attachments[$attachment_id])) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }

        $attachment = $attachments[$attachment_id];

        return response()->streamDownload(function () use ($attachment) {
            echo $attachment->content;
        }, $attachment->name);
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


    private function decodeMimeStr($string, $charset = 'UTF-8')
    {
        $newString = '';
        $elements = imap_mime_header_decode($string);
        foreach ($elements as $element) {
            if (isset($element->charset) && $element->charset != 'default') {
                $newString .= iconv($element->charset, $charset, $element->text);
            } else {
                $newString .= $element->text;
            }
        }
        return $newString;
    }
}
