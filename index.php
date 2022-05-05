<?php
require("vendor/autoload.php");
$conf = [
    'service' => '',
    'port' => '',
    'user' => '',
    'passord' => '',
];
$obj = new phpEmail\phpEmail\SendMail($conf['service'], $conf['port'], $conf['user'], $conf['passord']);

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
