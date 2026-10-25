<?php
include('phpmailer.php');
class Mail extends PhpMailer
{
    // Set default variables for all new objects
    public $From = 'karavaanhunt@iplug.co.in';
    public $FromName = 'KARAVAAN';






    public $WordWrap = 75;

    public function subject($subject)
    {
        $this->Subject = $subject;
    }

    public function body($body)
    {
        $this->Body = $body;
    }

    public function send()
    {
        $this->AltBody = strip_tags(stripslashes($this->Body))."\n\n";
        $this->AltBody = str_replace("&nbsp;", "\n\n", $this->AltBody);
        if (getenv('APP_ENV') === 'test') {
            $testDatabase = getenv('TEST_DB_NAME');
            $capturePath = getenv('TEST_MAIL_CAPTURE');
            $runtimeDirectory = realpath(__DIR__ . '/../../tests/.runtime');
            if (PHP_SAPI !== 'cli-server'
                || !defined('DBNAME')
                || !is_string($testDatabase)
                || preg_match('/\Atest(?:_[A-Za-z0-9]+)+\z/D', $testDatabase) !== 1
                || DBNAME !== $testDatabase
                || $runtimeDirectory === false
                || $capturePath !== $runtimeDirectory . '/mail.jsonl') {
                return false;
            }
            $message = json_encode(array('subject' => $this->Subject, 'body' => $this->Body));
            return $message !== false
                && file_put_contents($capturePath, $message . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
        }
        return parent::send();
    }
}