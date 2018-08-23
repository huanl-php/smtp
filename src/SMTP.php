<?php
/**
 *============================
 * author:Farmer
 * time:2018/8/22 14:15
 * blog:blog.icodef.com
 * function:smtp发信协议
 *============================
 */


namespace HuanL\Protocol;

class SMTP extends Client {

    /**
     * 是否输入内容
     * @var bool
     */
    protected $is_input_data = false;

    /**
     * 头
     * @var array
     */
    public $headers = [];
    /**
     * 发件者
     * @var array
     */
    protected $from = ['', ''];

    /**
     * 第一次发送内容
     * @var bool
     */
    protected $frist_content = true;

    /**
     * 收件者
     * @var array
     */
    protected $to = [];

    /**
     * SMTP constructor.
     * @param string $server
     * @param string $user
     * @param string $pwd
     * @param int $port
     * @throws SMTPException
     */
    public function __construct(string $server, string $user, string $pwd, int $port = 25) {
        parent::__construct($server, $port, 1);
        //两秒超时
        $this->timeout(2, 2);
        $read = $this->read(1024);
        if (($code = substr($read, 0, 3)) != '220') {
            //不是smtp服务器,没给我们打招呼,抛出异常
            throw new SMTPException('smtp server returns a reject code', $code);
        }
        $this->login($user, $pwd);
    }

    /**
     * 登录
     * @param $user
     * @param $pwd
     * @throws SMTPException
     */
    protected function login($user, $pwd) {
        $input = ['username:' => $user, 'password:' => $pwd];
        //登录用户
        $this->sendCommand('helo ' . $user);
        $read = $this->readCommand('250', 'login error');

        $this->sendCommand('auth login');
        $read = $this->readCommand('334', ' login error');

        $key = strtolower(base64_decode(substr($read, strpos($read, ' ') + 1)));
        $this->sendCommand(base64_encode($input[$key]));
        $read = $this->readCommand('334', $input[$key] . ' login error');

        $key = strtolower(base64_decode(substr($read, strpos($read, ' ') + 1)));
        $this->sendCommand(base64_encode($input[$key]));
        $this->readCommand('235', $input[$key] . ' login error');
    }

    /**
     * 读取命令行
     * @param $code
     * @param string $errmsg
     * @return string
     * @throws SMTPException
     */
    public function readCommand($code, $errmsg = '') {
        $read = $this->read(1024);
        if (($errcode = substr($read, 0, 3)) != $code) {
            throw new SMTPException($errmsg, $errcode);
        }
        return trim($read);
    }

    /**
     * 发送一条命令
     * @param string $data
     * @return int
     */
    public function sendCommand(string $data): int {
        return parent::send($data . "\r\n"); // TODO: Change the autogenerated stub
    }

    /**
     * 发件者,第二个参数是名字 别人可以看到这样 $name<$form> 的格式
     * @param string $from
     * @param string $name
     * @return SMTP
     */
    public function mailFrom(string $from, string $name = ''): SMTP {
        $this->from = [$from, $name];
        $this->sendCommand('mail from:<' . $from . '>');
        $this->readCommand('250', 'error mail from');
        return $this;
    }

    /**
     * 接收者,对方的邮箱
     * @param string $to
     * @return SMTP
     */
    public function mailTo(string $to): SMTP {
        $this->to[] = $to;
        $this->sendCommand('rcpt to:<' . $to . '>');
        $this->readCommand('250', 'error mail to');
        return $this;
    }

    /**
     * 编码
     * @param $data
     * @return string
     */
    protected function encode($data): string {
        return '=?utf-8?b?' . base64_encode($data) . '?=';
    }

    /**
     * 邮箱头
     * @param array $headers
     * @return $this
     */
    public function mailHeaders(array $headers = []) {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    protected function sendHeaders() {
        foreach ($this->headers as $key => $value) {
            $this->sendCommand($key . ':' . $value);
        }
    }

    /**
     * 邮件标题
     * @param $title
     * @return $this
     */
    public function mailTitle($title) {
        $this->mailHeaders(['subject' => $this->encode($title)]);
        return $this;
    }

    /**
     * 处理默认的header
     */
    protected function dealDefaultHeaders() {
        if (!isset($this->headers['from'])) {
            $this->headers['from'] = (empty($this->from[1]) ?: $this->encode($this->from[1])) . '<' . $this->from[0] . '>';
        }
        if (!isset($this->headers['to'])) {
            $this->headers['to'] = '';
            foreach ($this->to as $value) {
                $this->headers['to'] .= '<' . $value . '>,';
            }
            $this->headers['to'] = substr($this->headers['to'], 0, strlen($this->headers['to']) - 1);
        }
        if (!isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = 'text/html; charset=utf-8';
        }
        if (!isset($this->headers['Content-Transfer-Encoding'])) {
            $this->headers['Content-Transfer-Encoding'] = 'base64';
        }
    }

    /**
     * 邮件内容
     * @param $content
     * @return $this
     */
    public function mailContent($content) {
        if (!$this->is_input_data) {
            $this->is_input_data = true;
            $this->sendCommand('data');
            $this->readCommand('354', 'data input error');
            $this->dealDefaultHeaders();
            $this->sendHeaders();
        }
        $content = base64_encode($content);
        if ($this->frist_content) {
            $content = "\r\n" . $content;
            $this->frist_content = false;
        }
        $this->sendCommand($content);
        return $this;
    }

    /**
     * 发送邮件
     */
    public function sendMail() {
        if (!$this->is_input_data) {
            throw new SMTPException('content is null', 0);
        }
        $this->sendCommand("\r\n.");
        $this->readCommand('250', 'end error');
        $this->sendCommand("quit");
        $this->readCommand('221', 'quit error');
        return $this;
    }
}

