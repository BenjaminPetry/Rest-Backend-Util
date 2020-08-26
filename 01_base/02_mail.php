<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

class Mail
{
    public static function send($to, $subject, $message, $from=null)
    {
        $from = ($from) ? $from : CONFIG_EMAIL_DEFAULT_SENDER;

        // To send HTML mail, the Content-type header must be set
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'From: ' . $from . "\r\n" .
              'Reply-To: ' . $from . "\r\n" .
              'X-Mailer: PHP/' . phpversion();

        $message .= str_replace("&nbsp;", " ", str_replace("<br>", "\n", str_replace("<br />", "\n", str_replace("\r\n", "\n", $message))));
        return mail($to, $subject, $message, $headers);
    }
}
