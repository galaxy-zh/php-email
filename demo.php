<?php
require("vendor/autoload.php");
$conf = [
    'service' => 'smtp.qq.com',
    'port' => '465',
    'user' => '1603649280@qq.com',
    'passord' => 'powwbtrnqgjcgdhb',
];
$obj = new \PhpEmail\PhpEmail\SendMail($conf['service'], $conf['port'], $conf['user'], $conf['passord']);

//构建邮件
$mail = array(
    "subject" => "test",
    "body" => array(
        'content' => "hi 这是一个测试邮件",
    )
);
$mailto = "369124067@qq.com";
$res = $obj->sendMail($mailto, $mail, true);
var_dump($res);
