<?php
/*
** Here's a class that implements sending HTML email using sendgrid v3 API, no
**    template required.
**
**   Example usage:
**
**    $sendgrid = new Sendgrid_Email($apiKey); // instantiate class and pass your sendgrid API key.
**    $result = $sendgrid->msg('someone@anywhere.net', 'The subject', 'The message');
**    print_r($result);
**
**    You can send to multiple emails using comma separated list:
**
**    $sendgrid = new Sendgrid_Email($apiKey); // instantiate class and pass your sendgrid API key.
**    $result = $sendgrid->msg('someone@anywhere.net, someoneelse@anywhere.net', 'The subject', 'The message');
**    print_r($result);
*/

class Sendgrid_Email
{
    /*
    ** Send Email using sendgrid
    */
    private $apiKey = null;

    function __construct($apiKey)
    {

        $this->apiKey = $apiKey;
    }

    function exception($msg)
    {

        throw new Exception('Sendgrid_Email: ' . $msg);
    }

    function showVar($var)
    {
        echo '<pre>' . print_r($var, true) . '</pre>';
    }

    // given comma separated email list, creates an array of arrays
    private function createEmailArray($list)
    {

        $result = array();

        $emails = explode(',', $list);

        foreach ($emails as $email) {

            $result[] = array('email' => trim($email));
        }

        return $result;
    }

    // fromName can be email address or regular name.  if regular name, then fromEmail is used as address
    function msg($to, $subject, $message, $fromName = '', $fromEmail = '')
    {
        if (empty($this->apiKey)) $this->exception('no api key.  you must configure a sendgrid api key');

        if (empty($subject)) $this->exception('a subject is required');
        if (empty($message)) $this->exception('a message is required');

        if ($fromEmail == '') {

            $from = parse_url(site_url());

            if ($fromName == '') {
                $fromEmail = 'do-not-reply@' . $from['host'];
                $fromName = $from['host'];
            } elseif (!filter_var($fromName, FILTER_VALIDATE_EMAIL)) {
                $fromEmail = sanitize_title($fromName) . '@' . $from['host'];
            } else {
                $fromEmail = $fromName;
                $fromName = $from['host'];
            }
        }

        $result = array(
            'success' => true,
            'errorMsg' => '',
        );

        $to = $this->createEmailArray($to);

        $data = array(
            'personalizations' =>
            array(
                array(
                    'to' => $to,
                    'subject' => $subject,
                ),
            ),
            'from' =>
            array(
                'email' => $fromEmail,
                'name'  => $fromName
            ),
            'content' =>
            array(
                array(
                    'type' => 'text/html',
                    'value' => $message,
                ),
            ),
        );

        $dataEncode = json_encode($data);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.sendgrid.com/v3/mail/send",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $dataEncode,
            CURLOPT_HTTPHEADER => array(
                "authorization: Bearer ".$this->apiKey,
                "content-type: application/json"
            ),
        ));

        $response = json_decode(curl_exec($curl));

        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            $result['success'] = false;
            $result['errorMsg'] = 'cURL Error';
            $result['errorDetails'] = $err;
        } elseif (!empty($response->errors)) {
            $result['success'] = false;
            $result['errorMsg'] = $response->errors[0]->message;
            $result['errorDetails'] = $response->errors[0];
        }

        return $result;
    }
}
