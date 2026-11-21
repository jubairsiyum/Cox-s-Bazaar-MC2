<?php

namespace App\Support;

/**
 * Pure PHP (no framework/database/session/auth calls) so it is safe to use
 * while the app itself is failing (500) or in maintenance mode (503).
 */
class ErrorPage
{
    /** tone: brand|warning|danger  -> colour family used by the scene
     *  emblem: radar|lock|cog|clock|gauge|tool|ban|alert -> hovering badge
     *  retry: seconds for the countdown (null = no countdown); auto: reload automatically at 0 */
    private const TABLE = [
        '400' => ['tone' => 'brand',   'emblem' => 'alert',  'retry' => null, 'auto' => false, 'button' => false,
            'bn' => ['title' => 'অনুরোধটি সঠিক নয়', 'message' => 'সার্ভার অনুরোধটি বুঝতে পারেনি। তথ্যগুলো যাচাই করে আবার চেষ্টা করুন।'],
            'en' => ['title' => 'Bad request', 'message' => 'The server could not understand the request. Please check the details and try again.']],
        '401' => ['tone' => 'warning', 'emblem' => 'lock',   'retry' => null, 'auto' => false, 'button' => false,
            'bn' => ['title' => 'লগইন প্রয়োজন', 'message' => 'এই পেজটি দেখতে আগে আপনার অ্যাকাউন্টে লগইন করতে হবে।'],
            'en' => ['title' => 'Sign in required', 'message' => 'You need to sign in to your account before you can view this page.']],
        '403' => ['tone' => 'warning', 'emblem' => 'lock',   'retry' => null, 'auto' => false, 'button' => false,
            'bn' => ['title' => 'প্রবেশের অনুমতি নেই', 'message' => 'এই পেজ দেখার অনুমতি আপনার নেই। ভুল মনে হলে অ্যাডমিনের সাথে যোগাযোগ করুন।'],
            'en' => ['title' => 'Access denied', 'message' => 'You do not have permission to view this page. If you think this is a mistake, contact an administrator.']],
        '404' => ['tone' => 'brand',   'emblem' => 'radar',  'retry' => null, 'auto' => false, 'button' => false,
            'bn' => ['title' => 'পেজটি খুঁজে পাওয়া যায়নি', 'message' => 'আপনি যে পেজটি খুঁজছেন সেটি সরানো হয়েছে, নাম বদলেছে, অথবা কখনো ছিলই না।'],
            'en' => ['title' => 'Page not found', 'message' => 'The page you are looking for was moved, renamed, or never existed.']],
        '405' => ['tone' => 'brand',   'emblem' => 'ban',    'retry' => null, 'auto' => false, 'button' => false,
            'bn' => ['title' => 'এই পদ্ধতি অনুমোদিত নয়', 'message' => 'এই ঠিকানায় এই ধরনের অনুরোধ পাঠানো যায় না। পিছনে গিয়ে আবার চেষ্টা করুন।'],
            'en' => ['title' => 'Method not allowed', 'message' => 'This address does not accept that kind of request. Go back and try again.']],
        '408' => ['tone' => 'warning', 'emblem' => 'clock',  'retry' => 10,   'auto' => false, 'button' => true,
            'bn' => ['title' => 'অনুরোধের সময় শেষ', 'message' => 'সার্ভার সময়মতো সাড়া পায়নি। ইন্টারনেট সংযোগ দেখে আবার চেষ্টা করুন।'],
            'en' => ['title' => 'Request timed out', 'message' => 'The server did not get a response in time. Check your connection and try again.']],
        '410' => ['tone' => 'brand',   'emblem' => 'radar',  'retry' => null, 'auto' => false, 'button' => false,
            'bn' => ['title' => 'পেজটি আর নেই', 'message' => 'এই কনটেন্ট স্থায়ীভাবে মুছে ফেলা হয়েছে।'],
            'en' => ['title' => 'Gone for good', 'message' => 'This content has been permanently removed.']],
        '419' => ['tone' => 'warning', 'emblem' => 'clock',  'retry' => null, 'auto' => false, 'button' => true,
            'bn' => ['title' => 'সেশনের মেয়াদ শেষ', 'message' => 'নিরাপত্তার জন্য আপনার সেশন শেষ হয়ে গেছে। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।'],
            'en' => ['title' => 'Session expired', 'message' => 'For your security the session has expired. Refresh the page and try again.']],
        '422' => ['tone' => 'brand',   'emblem' => 'alert',  'retry' => null, 'auto' => false, 'button' => false,
            'bn' => ['title' => 'তথ্য প্রক্রিয়া করা যায়নি', 'message' => 'পাঠানো তথ্যে সমস্যা আছে। ফর্মটি যাচাই করে আবার জমা দিন।'],
            'en' => ['title' => 'Could not process', 'message' => 'Something in the submitted data is not valid. Review the form and submit again.']],
        '429' => ['tone' => 'warning', 'emblem' => 'gauge',  'retry' => 30,   'auto' => true,  'button' => true,
            'bn' => ['title' => 'অনেক বেশি অনুরোধ', 'message' => 'অল্প সময়ে অনেকগুলো অনুরোধ পাঠানো হয়েছে। একটু অপেক্ষা করুন, আমরা নিজে থেকেই আবার চেষ্টা করব।'],
            'en' => ['title' => 'Too many requests', 'message' => 'You sent a lot of requests in a short time. Hold on a moment, we will retry automatically.']],
        '500' => ['tone' => 'danger',  'emblem' => 'cog',    'retry' => null, 'auto' => false, 'button' => true,
            'bn' => ['title' => 'সার্ভারে সমস্যা হয়েছে', 'message' => 'আমাদের দিক থেকে কিছু একটা ভুল হয়েছে। আমরা বিষয়টি দেখছি, একটু পরে আবার চেষ্টা করুন।'],
            'en' => ['title' => 'Something went wrong', 'message' => 'Something broke on our side. We are looking into it. Please try again in a moment.']],
        '502' => ['tone' => 'danger',  'emblem' => 'cog',    'retry' => 20,   'auto' => true,  'button' => true,
            'bn' => ['title' => 'গেটওয়ে সমস্যা', 'message' => 'সার্ভার থেকে সঠিক উত্তর পাওয়া যায়নি। কিছুক্ষণ পর নিজে থেকেই আবার চেষ্টা হবে।'],
            'en' => ['title' => 'Bad gateway', 'message' => 'We got an invalid response from the server. We will retry automatically shortly.']],
        '503' => ['tone' => 'warning', 'emblem' => 'tool',   'retry' => 30,   'auto' => true,  'button' => true,
            'bn' => ['title' => 'রক্ষণাবেক্ষণ চলছে', 'message' => 'আমরা সিস্টেম আপডেট করছি। কাজ শেষ হলে পেজটি নিজে থেকেই চালু হয়ে যাবে।'],
            'en' => ['title' => 'Under maintenance', 'message' => 'We are updating the system. This page will come back on its own when we are done.']],
        '504' => ['tone' => 'danger',  'emblem' => 'clock',  'retry' => 20,   'auto' => true,  'button' => true,
            'bn' => ['title' => 'সার্ভার সাড়া দিচ্ছে না', 'message' => 'সার্ভার উত্তর দিতে অনেক দেরি করছে। কিছুক্ষণ পর নিজে থেকেই আবার চেষ্টা হবে।'],
            'en' => ['title' => 'Server timed out', 'message' => 'The server took too long to answer. We will retry automatically shortly.']],
    ];

    private const DEFAULT_4XX = ['tone' => 'brand', 'emblem' => 'alert', 'retry' => null, 'auto' => false, 'button' => false,
        'bn' => ['title' => 'অনুরোধটি সম্পন্ন করা যায়নি', 'message' => 'একটি সমস্যা হয়েছে। পিছনে গিয়ে আবার চেষ্টা করুন।'],
        'en' => ['title' => 'Request failed', 'message' => 'Something went wrong with this request. Go back and try again.']];

    private const DEFAULT_5XX = ['tone' => 'danger', 'emblem' => 'cog', 'retry' => null, 'auto' => false, 'button' => true,
        'bn' => ['title' => 'সার্ভারে সমস্যা হয়েছে', 'message' => 'আমাদের দিক থেকে কিছু একটা ভুল হয়েছে। একটু পরে আবার চেষ্টা করুন।'],
        'en' => ['title' => 'Something went wrong', 'message' => 'Something broke on our side. Please try again in a moment.']];

    private const UI = [
        'bn' => ['home' => 'হোমে ফিরুন', 'back' => 'পিছনে যান', 'retry' => 'আবার চেষ্টা করুন',
            'retrying' => '{s} সেকেন্ডে আবার চেষ্টা হবে', 'error' => 'এরর', 'reference' => 'রেফারেন্স', 'stalled' => 'স্বয়ংক্রিয় চেষ্টা থেমে গেছে'],
        'en' => ['home' => 'Go home', 'back' => 'Go back', 'retry' => 'Try again',
            'retrying' => 'Retrying in {s}s', 'error' => 'Error', 'reference' => 'Reference', 'stalled' => 'Automatic retry stopped'],
    ];

    /**
     * @param  int  $code  HTTP status
     * @param  object|null  $exception  Symfony/Laravel HttpException (only Retry-After is read from it)
     * @param  string|null  $locale  'bn' => Bangla, anything else => English
     * @param  string|null  $detail  optional extra line you chose to show (never pass 5xx exception messages)
     * @param  int|null  $retryAfter  explicit seconds (e.g. `php artisan down --render=... --retry=60` passes $retryAfter to the view)
     * @return array<string,mixed>
     */
    public static function resolve(int $code, $exception = null, ?string $locale = 'bn', ?string $detail = null, ?int $retryAfter = null): array
    {
        $code = ($code >= 400 && $code <= 599) ? $code : 500;
        $meta = self::TABLE[(string) $code] ?? ($code >= 500 ? self::DEFAULT_5XX : self::DEFAULT_4XX);
        $lang = ($locale === 'bn' || strpos((string) $locale, 'bn') === 0) ? 'bn' : 'en';

        // Honour a real Retry-After header (php artisan down --retry=60, throttle middleware, ...)
        $retry = $meta['retry'];
        if (is_object($exception) && method_exists($exception, 'getHeaders')) {
            $headers = array_change_key_case((array) $exception->getHeaders(), CASE_LOWER);
            if (isset($headers['retry-after']) && ctype_digit((string) $headers['retry-after'])) {
                $retry = max(1, min(3600, (int) $headers['retry-after']));
            }
        }

        if ($retryAfter !== null && $retryAfter > 0 && $meta['retry'] !== null) {
            $retry = min(3600, $retryAfter);
        }

        $reference = $_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['HTTP_X_AMZN_TRACE_ID'] ?? null;
        $reference = is_string($reference) ? substr(preg_replace('/[^A-Za-z0-9\-_=:.]/', '', $reference), 0, 40) : null;

        return [
            'code' => $code,
            'tone' => $meta['tone'],
            'emblem' => $meta['emblem'],
            'title' => $meta[$lang]['title'],
            'message' => $meta[$lang]['message'],
            'detail' => $detail !== null && $detail !== '' ? $detail : null,
            'retry' => $retry,
            'auto' => $retry !== null && $meta['auto'],
            'button' => $meta['button'],
            'retryText' => $retry !== null ? str_replace('{s}', self::digits((string) $retry, $lang), self::UI[$lang]['retrying']) : null,
            'ui' => self::UI[$lang],
            'locale' => $lang,
            'reference' => $reference ?: null,
            'time' => date('d M Y, h:i A'),
        ];
    }

    private static function digits(string $n, string $lang): string
    {
        return $lang === 'bn' ? strtr($n, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']) : $n;
    }
}
