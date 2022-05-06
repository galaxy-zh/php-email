<?php

namespace PhpEmail\PhpEmail;

class SendMail
{
    private $server;
    private $port;
    private $user;
    private $password;
    private $socket;
    private $debug;
    private $mime_boundary = "----=_NextPart_5ADB22FC_0AC35138_76A8687D";

    /**
     * @Description:初始化
     * @param String $server   SMTP服务器)
     * @param String $port     通信端口
     * @param String $user     账户
     * @param String $password 密码（注意部分邮箱，如QQ邮箱为授权密码）
     * @param bool   $isSsl    是否启用SSL,默认不启用
     * @param bool   $debug    开启后可以查看整个协议通信过程,默认False
     * @return void
     */
    public function __construct($server, $port, $user, $password, $isSsl = false, $debug = false)
    {
        if ($isSsl)
            $this->server = "ssl://" . $server;
        else
            $this->server = $server;
        $this->port = $port;
        $this->user = base64_encode($user);
        $this->password = base64_encode($password);
        $this->isSsl = $isSsl;
        $this->debug = $debug;
    }

    /**
     * @Description:发送邮件
     * @param String  $receiver
     * @param array  $mail
     * @return void
     */
    public function sendMail($receiver, $mailContent, $hasAttach = false)
    {
        $this->socketConnect('220');
        //PHP_EOF：<CRLF>保证平台兼容性
        $this->socketWrite("HELO JunQiu" . PHP_EOL, '250');
        $this->socketWrite("AUTH LOGIN" . PHP_EOL, '334');
        $this->socketWrite($this->user . PHP_EOL, '334');
        $this->socketWrite($this->password . PHP_EOL, '235');
        $this->socketWrite("MAIL FROM:<" . base64_decode($this->user) . ">" . PHP_EOL, '250');
        $this->socketWrite("RCPT TO:<" . $receiver . ">" . PHP_EOL, '250');
        $this->socketWrite("DATA" . PHP_EOL, '354');
        //构建邮件
        $mail = 'From:<' . base64_decode($this->user) . '>' . PHP_EOL;
        $mail .= 'To:<' . $receiver . '>' . PHP_EOL;
        $mail .= 'Subject:' . $mailContent['subject'] . PHP_EOL;
        if ($hasAttach)
            $mail .= 'Content-Type: multipart/mixed;' . PHP_EOL;   //范围最大，携带附件
        else
            $mail .= 'Content-Type: multipart/related;' . PHP_EOL; //各种正文和内嵌资源
        $mail .= "    boundary=\"$this->mime_boundary\"" . PHP_EOL;
        $mail .= 'MIME-Version: 1.0' . PHP_EOL;
        //$mail.="Date:".date(DATE_RFC2822).PHP_EOL;
        $mail .= "Content-Transfer-Encoding: 8Bit" . PHP_EOL;
        $mail .= PHP_EOL . "This is a multi-part message in MIME format." . PHP_EOL;
        //邮件内容以bounary为分界
        $mail .= PHP_EOL . '--' . $this->mime_boundary . PHP_EOL;
        //正文格式暂时统一如此处理,以后再增加各种格式
        $mail .= "Content-Type: text/html;" . PHP_EOL . "    charset=\"utf-8\"" . PHP_EOL . "Content-Transfer-Encoding: base64" . PHP_EOL;
        $mail .= PHP_EOL . base64_encode($mailContent['body']['content']) . PHP_EOL;
        //添加附件,可以添加多个
        if ($hasAttach) {
            foreach ($mailContent['attach'] as $k => $v) {
                $mail .= $this->creatMailAttach($v);
            }
        }
        $mail .= PHP_EOL . "--$this->mime_boundary--";
        $this->socketWrite($mail . PHP_EOL . '.' . PHP_EOL, '250');
        $this->socketWrite("QUIT" . PHP_EOL, '221');
        return true;
    }

    /**
     * @Description:构建邮件附件
     * @param String  $attachUrl  //文件地址
     * @return  String  $attach
     */
    public function creatMailAttach($attachUrl)
    {
        if (!file_exists($attachUrl))
            $this->mailErr('文件错误', "$attachUrl 不存在");
        //PHP5.3被废弃
        //$type=mime_content_type($attachUrl);
        $finfo = new finfo(FILEINFO_MIME);
        //检测是否开启fileinfo扩展
        if ($finfo  && extension_loaded('fileinfo')) {
            $fileInfo = $finfo->file($attachUrl);
            $fileInfo = explode(" ", $fileInfo);
            $type = $fileInfo[0];
            $charset = $fileInfo[1];
        } else {
            $type = "application/octet-stream;";
            $charset = "utf-8";
        }
        //exit(0);
        $base64Data = base64_encode(file_get_contents($attachUrl));
        $attach = PHP_EOL . '--' . $this->mime_boundary . PHP_EOL;
        $attach .= "Content-Type: " . $type . PHP_EOL . "    charset=\"" . $charset . "\";" . PHP_EOL . "    name=\"" . basename($attachUrl) . "\"" . PHP_EOL . "Content-Disposition: attachment; filename=\"" . basename($attachUrl) . "\"" . PHP_EOL . "Content-Transfer-Encoding: base64" . PHP_EOL;
        $attach .= PHP_EOL . $base64Data . PHP_EOL;
        return $attach;
    }

    /**
     * @Description:建立连接
     * @return void
     */
    public function socketConnect($code)
    {
        $this->socket = fsockopen($this->server, $this->port, $errno, $errstr);
        $codeResult = $this->socketRead();
        //检验返回码是否正确
        if ($code != $codeResult[0])
            $this->mailErr($codeResult[0], $codeResult[1]);
    }

    /**
     * @Description:向流中写入数据
     * @param String $message
     * @param String $code
     * @return void
     */
    public function socketWrite($message, $code)
    {
        $result = fwrite($this->socket, $message);
        if (!$result) {
            $this->mailErr('Write Error', "写入数据失败");
        }
        if ($this->debug)
            echo "$message<br/>";
        $codeResult = $this->socketRead();
        //检验返回码是否正确
        if ($code != $codeResult[0])
            $this->mailErr($codeResult[0], $codeResult[1]);
    }

    /**
     * @Description:从流中读数据
     * @return String $code
     */
    public function socketRead()
    {
        $result = fgets($this->socket);
        if (!$result)
            $this->mailErr('Read Error', "读取数据失败");
        //SMTP服务返回前3位为code,preg_match需要有定界符
        preg_match('/\d{3}/', $result, $code);
        $code[1] = $result;
        if ($this->debug)
            echo "$result<br/>";
        return $code;
    }

    /**
     * @Description:错误处理
     * @param String  $code
     * @param String  $errMessage
     * @return void
     */
    public function mailErr($code, $errMessage)
    {
        echo $code . "  errorMessage:" . $errMessage;
        exit(0);
    }
}
