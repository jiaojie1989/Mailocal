<?php

/*
 * This file is part of the Maillocal package.
 *
 * Copyright 2019 Jonathan Foucher
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @package Mailocal
 */

namespace App\EventSubscriber;

use App\Email\InvalidAttachmentException;
use App\Email\Parser;
use App\Entity\Email;
use App\Smtp\AuthFailedEvent;
use App\Smtp\CustomSession;
use App\Smtp\MessageReceivedEvent;
use App\Smtp\SessionInterface;
use Doctrine\ORM\EntityManagerInterface;
use MS\Email\Parser\Attachment;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use ZBateson\MailMimeParser\MailMimeParser;

class SmtpReceivedSubscriber implements EventSubscriberInterface
{
    protected $logger;
    protected $em;

    public function __construct(LoggerInterface $logger, EntityManagerInterface $em)
    {
        $this->logger = $logger;
        $this->em = $em;
    }

    public static function getSubscribedEvents()
    {
        return [
            CustomSession::EVENT_SMTP_RECEIVED => [
                ['processSmtpConnection', 10],
            ],
            CustomSession::EVENT_SMTP_AUTH_FAILED => [
                ['processAuthFailed', 10],
            ],
        ];
    }

    public function processSmtpConnection(MessageReceivedEvent $event)
    {
        $event->message->delivered = true;
        $newParser = new MailMimeParser();
        try {
            $newMessage = $newParser->parse($event->message->data);
            //$message = $parser->parse($event->message->data);
        } catch (InvalidAttachmentException $e) {
            $this->logger->error('Email has invalid attachment '.$e->getMessage().' - '.json_encode($event->message));
            return;
        }
        $email = new Email();
        $headers = $newMessage->getAllHeaders();
        $headersStr = '';
        foreach($headers as $header) {
            $headersStr .= $header->getName() . ": " . $header->getValue() . "\n";
        }
        $email->setHeaders($headersStr);
        $email->setRaw($event->message->data);
        $email->setHtml($newMessage->getHtmlContent());
        $email->setText($newMessage->getTextContent());
        $attachments = [];
        if ($newMessage->getAttachmentCount() > 0) {
            $attachments = array_map(function ($item) {
                /**
                 * @var Attachment $item
                 */
                return [
                    'filename' => preg_replace('/\s/', '', urldecode(mb_decode_mimeheader($item->getFilename()))),
                    'contents' => base64_encode($item->getContent()),
                    'mimetype' => $item->getMimeType(),
                    'type' => $this->mapTypes($item->getContentType()),
                ];
            }, $newMessage->getAllAttachmentParts());
        }
        $email->setAttachments($attachments);
        $email->setSubject($newMessage->getHeader('Subject')->getValue());
        $email->setFrom($newMessage->getHeader('From')->getValue());
        $email->setFromName($newMessage->getHeader('From')->getName());
        $tos = $newMessage->getHeader('To');
        $toArr = [];
        foreach($tos->getAddresses() as $to) {
            $toArr[] = $to->getValue();
        }
        $email->setTo(join(', ', $toArr));
        $email->setCreatedAt($newMessage->getHeader('Date')->getDateTime());
        try {
            $this->em->persist($email);
            $this->em->flush();
            $this->logger->info('Email saved');
        } catch (\Exception $e) {
            $this->logger->error('Email NOT saved');
        }
    }

    public function processAuthFailed(AuthFailedEvent $event)
    {
        $this->logger->error('Auth failed for user '.$event->username. ' with password '.$event->password);
    }

    protected function mapTypes($mimeType)
    {
        $map = [
            'application/vnd.ms-excel' => 'excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.template' => 'excel',
            'application/vnd.ms-excel.sheet.macroEnabled.12' => 'excel',
            'application/vnd.ms-excel.template.macroEnabled.12' => 'excel',
            'application/vnd.ms-excel.addin.macroEnabled.12' => 'excel',
            'application/vnd.ms-excel.sheet.binary.macroEnabled.1' => 'excel',
            'application/msword' => 'word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => 'word',
            'application/vnd.ms-word.document.macroEnabled.12' => 'word',
            'application/vnd.ms-word.template.macroEnabled.12' => 'word',
            'application/vnd.ms-powerpoint' => 'word',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'word',
            'application/vnd.openxmlformats-officedocument.presentationml.template' => 'word',
            'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => 'word',
            'application/vnd.ms-powerpoint.addin.macroEnabled.12' => 'word',
            'application/vnd.ms-powerpoint.presentation.macroEnabled.12' => 'word',
            'application/vnd.ms-powerpoint.slideshow.macroEnabled.12' => 'word',
            'application/pdf' => 'pdf',
            'application/x-bzip2' => 'archive',
            'application/zip' => 'archive',
            'application/vnd.ms-cab-compressed' => 'archive',
            'application/x-7z-compressed' => 'archive',
            'image/bmp' => 'image',
            'image/cis-cod' => 'image',
            'image/gif' => 'image',
            'image/ief' => 'image',
            'image/jpeg' => 'image',
            'image/pipeg' => 'image',
            'image/svg+xml' => 'image',
            'image/tiff' => 'image',
            'audio/mpeg' => 'audio',
            'audio/basic' => 'audio',
            'audio/mid' => 'audio',
            'audio/x-wav' => 'audio',
        ];
        if (isset($map[$mimeType])) {
            return $map[$mimeType];
        }
        return '';
    }
}
