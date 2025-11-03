<?php
/**
 * View shared/math_captcha.
 *
 * @var int    $num1 The first number in the math captcha.
 * @var int    $num2 The second number in the math captcha.
 * @var string $data The encrypted answer to the math captcha.
 */

defined('ABSPATH') || exit;

$random_id = uniqid();
$label_id  = "mepr_math_captcha-{$random_id}";

// Translators: %s is a placeholder for the math question.
$label_encoded = base64_encode(sprintf(__('%s equals?', 'memberpress'), "{$num1} + {$num2}"));
?>

<div class="mp-form-row mepr_math_captcha">
    <div class="mp-form-label">
        <label for="mepr_math_quiz">
            <span id="<?php echo esc_attr($label_id); ?>"></span>*
        </label>
    </div>

    <input type="text" name="mepr_math_quiz" id="mepr_math_quiz" value="" class="mepr-form-input" />
    <input type="hidden" name="mepr_math_data" value="<?php echo esc_attr($data); ?>" />

    <script>
    function mepr_base64_decode(encodedData) {
        var decodeUTF8string = function(str) {
            // Going backwards: from bytestream, to percent-encoding, to original string.
            return decodeURIComponent(str.split('').map(
                function(c) {
                    return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)
                })
                .join('')
            )
        }

        if (typeof window !== 'undefined') {
            if (typeof window.atob !== 'undefined') {
                return decodeUTF8string(window.atob(encodedData))
            }
        } else {
            return new Buffer(encodedData, 'base64').toString('utf-8')
        }

        var b64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/='
        var o1
        var o2
        var o3
        var h1
        var h2
        var h3
        var h4
        var bits
        var i = 0
        var ac = 0
        var dec = ''
        var tmpArr = []

        if (!encodedData) {
            return encodedData
        }

        encodedData += ''
        do {
            // unpack four hexets into three octets using index points in b64
            h1 = b64.indexOf(encodedData.charAt(i++))
            h2 = b64.indexOf(encodedData.charAt(i++))
            h3 = b64.indexOf(encodedData.charAt(i++))
            h4 = b64.indexOf(encodedData.charAt(i++))
            bits = h1 << 18 | h2 << 12 | h3 << 6 | h4
            o1 = bits >> 16 & 0xff
            o2 = bits >> 8 & 0xff
            o3 = bits & 0xff

            if (h3 === 64) {
                tmpArr[ac++] = String.fromCharCode(o1)
            } else if (h4 === 64) {
                tmpArr[ac++] = String.fromCharCode(o1, o2)
            } else {
                tmpArr[ac++] = String.fromCharCode(o1, o2, o3)
            }
        } while (i < encodedData.length)

        dec = tmpArr.join('')
        return decodeUTF8string(dec.replace(/\0+$/, ''))
    }

    jQuery(document).ready(function() {
        var el = document.getElementById("<?php echo esc_html($label_id); ?>")
        el.innerHTML = mepr_base64_decode("<?php echo esc_html($label_encoded); ?>");
    });
    </script>
</div>
