kcaptcha
========

KCaptcha fork for PHP 5.3+

Example:

    use kaptcha\KCaptcha;

    $captcha = new KCaptcha();
    $img = $captcha->render();

    // get captcha string
    $word = $captcha->getKeyString();

