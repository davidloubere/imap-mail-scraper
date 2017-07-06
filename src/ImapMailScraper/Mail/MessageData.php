<?php

namespace ImapMailScraper\Mail;

class MessageData
{
    /**
     * @var string
     */
    protected $uid;

    /**
     * @var string
     */
    protected $charset;

    /**
     * @var \DateTime
     */
    protected $date;

    /**
     * @var array
     */
    protected $from;

    /**
     * @var string
     */
    protected $subject;

    /**
     * @var array
     */
    protected $bodies;

    /**
     * @param string $uid
     */
    public function setUid($uid)
    {
        $this->uid = $uid;
    }

    /**
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @param string $charset
     */
    public function setCharset($charset)
    {
        $this->charset = $charset;
    }

    /**
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * @param \DateTime $date
     */
    public function setDate(\DateTime $date)
    {
        $this->date = $date;
    }

    /**
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param array $from
     */
    public function setFrom(array $from)
    {
        $this->from = $from;
    }

    /**
     * @return array
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @param string $subject
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param array $bodies
     */
    public function setBodies(array $bodies)
    {
        $this->bodies = $bodies;
    }

    /**
     * @return array
     */
    public function getBodies()
    {
        return $this->bodies;
    }
}
