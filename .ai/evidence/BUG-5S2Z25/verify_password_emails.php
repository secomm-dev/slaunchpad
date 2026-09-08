<?php
/**
 * BUG-5S2Z25 / SLP-115 — verify render email password reset (vi/en) qua store design config.
 * Chỉ RENDER, không gửi email. Dữ liệu customer = fake test data.
 */
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Email\Model\TemplateFactory;
use Magento\Store\Model\StoreManagerInterface;

require '/var/www/projects/slaunchpad/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();

set_exception_handler(function ($e) {
    fwrite(STDERR, 'EXC: ' . $e->getMessage() . PHP_EOL);
    exit(1);
});

/** @var State $state */
$state = $om->get(State::class);
$state->setAreaCode(Area::AREA_FRONTEND);

/** @var StoreManagerInterface $storeManager */
$storeManager = $om->get(StoreManagerInterface::class);
/** @var TemplateFactory $templateFactory */
$templateFactory = $om->get(TemplateFactory::class);

$checks = [
    'customer_password_forgot_email_template' => [
        'subject_vi' => 'Đặt lại mật khẩu',
        'body_vi' => ['Gần đây có yêu cầu đổi mật khẩu', 'hãy đặt mật khẩu mới tại đây', 'Đặt mật khẩu mới', 'bỏ qua email này và mật khẩu của bạn sẽ giữ nguyên'],
    ],
    'customer_password_reset_password_template' => [
        'subject_vi' => 'Mật khẩu',
        'body_vi' => ['Chúng tôi đã nhận được yêu cầu thay đổi thông tin', 'vui lòng liên hệ ngay với chúng tôi qua', 'hoặc gọi cho chúng tôi qua'],
    ],
];

foreach ([1 => 'vi', 2 => 'en'] as $storeId => $lang) {
    $store = $storeManager->getStore($storeId);
    $vars = [
        'store' => $store,
        'customer' => new \Magento\Framework\DataObject([
            'name' => 'QC Tester',
            'id' => '0',
            'rp_token' => 'faketoken123',
            'email' => 'qc-test@example.com',
        ]),
        'store_email' => 'support@example.com',
        'store_phone' => '0123456789',
    ];
    foreach ($checks as $templateId => $expect) {
        $template = $templateFactory->create();
        $template->loadDefault($templateId);
        $template->setDesignConfig(['area' => Area::AREA_FRONTEND, 'store' => $storeId]);
        $template->setVars($vars);
        $template->setOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId]);
        $subject = strip_tags($template->getProcessedTemplateSubject($vars));
        $body = $template->processTemplate();
        echo "=== store {$storeId} ({$lang}) :: {$templateId} ===" . PHP_EOL;
        echo 'SUBJECT: ' . $subject . PHP_EOL;
        if ($lang === 'vi') {
            $needles = array_merge([$expect['subject_vi']], $expect['body_vi']);
        } else {
            $needles = ['There was recently a request to change the password', 'Set a New Password', 'password has been changed', 'please contact us immediately'];
        }
        foreach ($needles as $needle) {
            $inSubject = str_contains($subject, $needle);
            $inBody = str_contains($body, $needle);
            echo ($inSubject || $inBody ? 'PASS' : 'FAIL') . "  {$needle}" . PHP_EOL;
        }
        if ($lang === 'vi') {
            foreach (['There was recently a request to change', 'If you requested this change', 'password will remain the same'] as $enLeak) {
                echo (str_contains($body, $enLeak) ? 'LEAK' : 'CLEAN') . "  EN-leak: {$enLeak}" . PHP_EOL;
            }
        }
        preg_match('/customer\/account\/createPassword\/[^"\s]*/', $body, $m);
        echo 'RESET LINK: ' . (!empty($m) ? 'present' : 'MISSING') . PHP_EOL;
        echo PHP_EOL;
    }
}
