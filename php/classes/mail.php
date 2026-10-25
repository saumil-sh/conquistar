<?php
class Mail
{
    private $from = '';
    private $to = '';
    private $subject = '';
    private $body = '';

    public function setFrom($address)
    {
        $this->from = $address;
    }

    public function addAddress($address)
    {
        $this->to = $address;
    }

    public function subject($subject)
    {
        $this->subject = $subject;
    }

    public function body($body)
    {
        $this->body = $body;
    }

    public function send()
    {
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
            $message = json_encode(array('subject' => $this->subject, 'body' => $this->body));
            return $message !== false
                && file_put_contents($capturePath, $message . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
        }

        // Validated addresses cannot contain line breaks, so they cannot add extra mail headers.
        if (!filter_var($this->to, FILTER_VALIDATE_EMAIL) || !filter_var($this->from, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $headers = array(
            'From' => $this->from,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8',
        );
        return mail($this->to, $this->subject, wordwrap($this->body, 75), $headers);
    }
}
