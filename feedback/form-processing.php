<?php

header('Content-Type: application/json');

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  exit();
}

define('LOG_FILE', 'logs/' . date('Y-m-d') . '.log');

const HAS_WRITE_LOG = true;

const HAS_CHECK_CAPTCHA = true;


const HAS_SEND_EMAIL = true;

const HAS_ATTACH_IN_BODY = false;
const EMAIL_SETTINGS = [
  'addresses' => ['doctor@crochet-capybaras.ru'], 
  'from' => ['no-reply@crochet-capybaras.ru', 'crochet-capybaras.ru'], 
  'subject' => 'Сообщение с формы обратной связи', 
  'host' => 'mail.crochet-capybaras.ru', 
  'username' => 'no-reply@crochet-capybaras.ru', 
  'password' => 'tesxdqlnvhwwvded', 
  'port' => '465' 
];
const HAS_SEND_NOTIFICATION = false;
const BASE_URL = 'https://www.crochet-capybaras.ru';
const SUBJECT_FOR_CLIENT = 'Ваше сообщение доставлено';
//
const HAS_WRITE_TXT = true;

function itc_log($message)
{
  if (HAS_WRITE_LOG) {
    error_log('Date:  ' . date('d.m.Y h:i:s') . '  |  ' . $message . PHP_EOL, 3, LOG_FILE);
  }
}

$data = [
  'errors' => [],
  'form' => [],
  'logs' => [],
  'result' => 'success'
];

$attachs = [];

if (!empty($_POST['name'])) {
  $data['form']['name'] = htmlspecialchars($_POST['name']);
} else {
  $data['result'] = 'error';
  $data['errors']['name'] = 'Заполните это поле.';
  itc_log('Не заполнено поле name.');
}

if (!empty($_POST['email'])) {
  $data['form']['email'] = $_POST['email'];
  if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $data['result'] = 'error';
    $data['errors']['email'] = 'Email не корректный.';
    itc_log('Email не корректный.');
  }
} else {
  $data['result'] = 'error';
  $data['errors']['email'] = 'Заполните это поле.';
  itc_log('Не заполнено поле email.');
}

if (!empty($_POST['message'])) {
  $data['form']['message'] = htmlspecialchars($_POST['message']);
  if (mb_strlen($data['form']['message'], 'UTF-8') < 20) {
    $data['result'] = 'error';
    $data['errors']['message'] = 'Это поле должно быть не меньше 20 cимволов.';
    itc_log('Поле message должно быть не меньше 20 cимволов.');
  }
} else {
  $data['result'] = 'error';
  $data['errors']['message'] = 'Заполните это поле.';
  itc_log('Не заполнено поле message.');
}

if (HAS_CHECK_CAPTCHA) {
  session_start();
  if ($_POST['captcha'] === $_SESSION['captcha']) {
    $data['form']['captcha'] = $_POST['captcha'];
  } else {
    $data['result'] = 'error';
    $data['errors']['captcha'] = 'Код не соответствует изображению.';
    itc_log('Не пройдена капча. Указанный код ' . $_POST['captcha'] . ' не соответствует ' . $_SESSION['captcha']);
  }
}


use PHPMailer\PHPMailer\PHPMailer;

use PHPMailer\PHPMailer\Exception;

require 'vendor/phpmailer/phpmailer/src/Exception.php';
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';

if ($data['result'] == 'success' && HAS_SEND_EMAIL == true) {
 
  $template = file_get_contents(dirname(__FILE__) . '/template/email.tpl');
  $search = ['%subject%', '%name%', '%email%', '%message%', '%date%'];
  $replace = [EMAIL_SETTINGS['subject'], $data['form']['name'], $data['form']['email'], $data['form']['message'], date('d.m.Y H:i')];
  $body = str_replace($search, $replace, $template);
  
  if (HAS_ATTACH_IN_BODY && count($attachs)) {
    $ul = 'Файлы, прикреплённые к форме:<ul>';
    foreach ($attachs as $attach) {
      $href = str_replace($_SERVER['DOCUMENT_ROOT'], '', $attach);
      $name = basename($href);
      $ul .= '<li><a href="' . BASE_URL . $href . '">' . $name . '</a></li>';

      $data['href'][] = BASE_URL . $href;
    }
    $ul .= '</ul>';
    $body = str_replace('%attachs%', $ul, $body);
  } else {
    $body = str_replace('%attachs%', '', $body);
  }
  $mail = new PHPMailer();
  try {
    
    $mail->isSMTP();
    $mail->Host = EMAIL_SETTINGS['host'];
    $mail->SMTPAuth = true;
    $mail->Username = EMAIL_SETTINGS['username'];
    $mail->Password = EMAIL_SETTINGS['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = EMAIL_SETTINGS['port'];

    $mail->setFrom(EMAIL_SETTINGS['from'][0], EMAIL_SETTINGS['from'][1]);
    foreach (EMAIL_SETTINGS['addresses'] as $address) {
      $mail->addAddress(trim($address));
    }

    if (!HAS_ATTACH_IN_BODY && count($attachs)) {
      foreach ($attachs as $attach) {
        $mail->addAttachment($attach);
      }
    }
    //Content
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isHTML(true);
    $mail->Subject = EMAIL_SETTINGS['subject'];
    $mail->Body = $body;
    $mail->send();
    itc_log('Форма успешно отправлена.');
  } catch (Exception $e) {
    $data['result'] = 'error';
    itc_log('Ошибка при отправке письма: ' . $mail->ErrorInfo);
  }
}

if ($data['result'] == 'success' && HAS_WRITE_TXT) {
  $output = '======= ' . date('d.m.Y H:i') . ' =======';
  $output .= PHP_EOL . 'Имя: ' . $data['form']['name'];
  $output .= PHP_EOL . 'Email: ' . $data['form']['email'];
  $output .= PHP_EOL . 'Сообщение: ' . $data['form']['message'] . PHP_EOL;
  $output .= '=====================
  ';
  error_log($output, 3, 'logs/forms.log');
}

echo json_encode($data);
exit();
?>