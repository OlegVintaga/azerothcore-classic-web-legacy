<?php
// PayPal IPN-обработчик модуля донатов.
// PHP 8 / mysqli: подключение к ВЕБ-базе сайта ($websitedb), paypal-таблицы
// живут там же. Игровые базы не используются.
include('core/class.mangosweb.php');
include('core/common.php');

// Super global.
$MW = new mangosweb;

/////////////////////////////////////////////////
/////////////Begin Script below./////////////////
/////////////////////////////////////////////////

function postv(string $k, string $def = ''): string
{
    return isset($_POST[$k]) && $_POST[$k] !== '' ? (string)$_POST[$k] : $def;
}

// read the post from PayPal system and add 'cmd'
$req = 'cmd=_notify-validate';
foreach ($_POST as $key => $value) {
    $value = urlencode(stripslashes((string)$value));
    $req .= "&$key=$value";
}

// post back to PayPal system to validate (HTTPS, TLS 1.2+)
$ctx = stream_context_create(array('ssl' => array(
    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
    'SNI_enabled'   => true,
)));
// If testing on Sandbox use: ssl://www.sandbox.paypal.com
$fp = @stream_socket_client('ssl://www.paypal.com:443', $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx);

// assign posted variables to local variables
$item_name          = postv('item_name');
$payment_status     = postv('payment_status');
$mc_gross           = postv('mc_gross');
$payment_currency   = postv('mc_currency');
$txn_id             = postv('txn_id');
$quantity           = postv('quantity');
$num_cart_items     = (int)postv('num_cart_items', '0');
$payment_date       = postv('payment_date');
$first_name         = postv('first_name');
$last_name          = postv('last_name');
$payment_type       = postv('payment_type');
$payment_gross      = postv('payment_gross');
$payment_fee        = postv('payment_fee');
$settle_amount      = postv('settle_amount');
$memo               = postv('memo');
$payer_email        = postv('payer_email');
$txn_type           = postv('txn_type');
$payer_status       = postv('payer_status');
$address_street     = postv('address_street');
$address_city       = postv('address_city');
$address_state      = postv('address_state');
$address_zip        = postv('address_zip');
$address_country    = postv('address_country');
$address_status     = postv('address_status');
$item_number        = postv('item_number');
$tax                = postv('tax');
$option_name1       = postv('option_name1');
$option_selection1  = postv('option_selection1');
$option_name2       = postv('option_name2');
$option_selection2  = postv('option_selection2');
$invoice            = postv('invoice');
$custom             = postv('custom');
$mc_currency        = postv('mc_currency');
$mc_fee             = postv('mc_fee');
$exchange_rate      = postv('exchange_rate');
$settle_currency    = postv('settle_currency');
$parent_txn_id      = postv('parent_txn_id');
$pending_reason     = postv('pending_reason');
$reason_code        = postv('reason_code');

// subscription specific vars
$subscr_id          = postv('subscr_id');
$subscr_date        = postv('subscr_date');
$subscr_effective   = postv('subscr_effective');
$period1            = postv('period1');
$period2            = postv('period2');
$period3            = postv('period3');
$amount1            = postv('amount1');
$amount2            = postv('amount2');
$amount3            = postv('amount3');
$mc_amount1         = postv('mc_amount1');
$mc_amount2         = postv('mc_amount2');
$mc_amount3         = postv('mc_amount3');
$recurring          = postv('recurring');
$reattempt          = postv('reattempt');
$retry_at           = postv('retry_at');
$recur_times        = postv('recur_times');
$username           = postv('username');
$password           = postv('password');

//DB connect creds and email
$notify_email = (string)$MW->getConfig->donation->PayPal_Email; //email address to which debug emails are sent to
$site_db     = $MW->getDbInfoSite; // веб-база сайта (paypal_* таблицы здесь)

if (!$fp) {
    // HTTP ERROR
    http_response_code(502);
    die('PayPal connection error: ' . $errstr);
}

$http  = "POST /cgi-bin/webscr HTTP/1.1\r\n";
$http .= "Host: www.paypal.com\r\n";
$http .= "Content-Type: application/x-www-form-urlencoded\r\n";
$http .= "Content-Length: " . strlen($req) . "\r\n";
$http .= "Connection: close\r\n\r\n";
fputs($fp, $http . $req);

$Connect = @mysqli_connect($site_db['db_host'], $site_db['db_username'], $site_db['db_password'],
                           $site_db['db_name'], (int)$site_db['db_port']);
if (!$Connect) {
    fclose($fp);
    die("Couldn't connect to MySQL: " . mysqli_connect_error());
}
mysqli_set_charset($Connect, 'utf8mb4');

while (!feof($fp)) {
    $res = fgets($fp, 1024);
    if (strcmp($res, "VERIFIED") == 0) {

        $fecha = date("Ymd");

        //check if transaction ID has been processed before
        $checkquery = mysqli_prepare($Connect, "select txnid from paypal_payment_info where txnid=?");
        mysqli_stmt_bind_param($checkquery, 's', $txn_id);
        mysqli_stmt_execute($checkquery);
        mysqli_stmt_store_result($checkquery);
        $nm = mysqli_stmt_num_rows($checkquery);
        mysqli_stmt_close($checkquery);

        if ($nm == 0){

            $strQuery = $struery = $strQuery2 = '';

            if ($txn_type == "cart"){
                $ins = mysqli_prepare($Connect, "insert into paypal_payment_info(paymentstatus,buyer_email,firstname,lastname,street,city,state,zipcode,country,mc_gross,mc_fee,memo,paymenttype,paymentdate,txnid,pendingreason,reasoncode,tax,datecreation) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                mysqli_stmt_bind_param($ins, 'sssssssssssssssssss', $payment_status,$payer_email,$first_name,$last_name,$address_street,$address_city,$address_state,$address_zip,$address_country,$mc_gross,$mc_fee,$memo,$payment_type,$payment_date,$txn_id,$pending_reason,$reason_code,$tax,$fecha);
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);

                for ($i = 1; $i <= $num_cart_items; $i++) {
                    $c_itemname   = postv('item_name'.$i);
                    $c_itemnumber = postv('item_number'.$i);
                    $c_on0        = postv('option_name1_'.$i);
                    $c_os0        = postv('option_selection1_'.$i);
                    $c_on1        = postv('option_name2_'.$i);
                    $c_os1        = postv('option_selection2_'.$i);
                    $c_quantity   = postv('quantity'.$i);

                    $ins2 = mysqli_prepare($Connect, "insert into paypal_cart_info(txnid,itemnumber,itemname,os0,on0,os1,on1,quantity,invoice,custom) values (?,?,?,?,?,?,?,?,?,?)");
                    mysqli_stmt_bind_param($ins2, 'ssssssssss', $txn_id,$c_itemnumber,$c_itemname,$c_os0,$c_on0,$c_os1,$c_on1,$c_quantity,$invoice,$custom);
                    mysqli_stmt_execute($ins2);
                    mysqli_stmt_close($ins2);
                }
            }elseif ($txn_type != "subscr_signup" && $txn_type != "subscr_payment"){
                $ins = mysqli_prepare($Connect, "insert into paypal_payment_info(paymentstatus,buyer_email,firstname,lastname,street,city,state,zipcode,country,mc_gross,mc_fee,itemnumber,itemname,os0,on0,os1,on1,quantity,memo,paymenttype,paymentdate,txnid,pendingreason,reasoncode,tax,datecreation) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,??)");
                mysqli_stmt_bind_param($ins, 'ssssssssssssssssssssssssss', $payment_status,$payer_email,$first_name,$last_name,$address_street,$address_city,$address_state,$address_zip,$address_country,$mc_gross,$mc_fee,$item_number,$item_name,$option_name1,$option_selection1,$option_name2,$option_selection2,$quantity,$memo,$payment_type,$payment_date,$txn_id,$pending_reason,$reason_code,$tax,$fecha);
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);
            }

            // send an email in any case
            echo "Verified";
            if($notify_email) mail($notify_email, "VERIFIED IPN", "$res\n $req\n $txn_type $txn_id");
        }
        else {
            if($notify_email) mail($notify_email, "VERIFIED DUPLICATED TRANSACTION", "$res\n $req\n $txn_id");
        }

        //subscription handling branch
        if ( $txn_type == "subscr_signup"  ||  $txn_type == "subscr_payment"  ) {

            // insert subscriber payment info into paypal_payment_info table
            $ins = mysqli_prepare($Connect, "insert into paypal_payment_info(paymentstatus,buyer_email,firstname,lastname,street,city,state,zipcode,country,mc_gross,mc_fee,memo,paymenttype,paymentdate,txnid,pendingreason,reasoncode,tax,datecreation) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($ins, 'sssssssssssssssssss', $payment_status,$payer_email,$first_name,$last_name,$address_street,$address_city,$address_state,$address_zip,$address_country,$mc_gross,$mc_fee,$memo,$payment_type,$payment_date,$txn_id,$pending_reason,$reason_code,$tax,$fecha);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);

            // insert subscriber info into paypal_subscription_info table
            $ins2 = mysqli_prepare($Connect, "insert into paypal_subscription_info(subscr_id,sub_event,subscr_date,subscr_effective,period1,period2,period3,amount1,amount2,amount3,mc_amount1,mc_amount2,mc_amount3,recurring,reattempt,retry_at,recur_times,username,password,payment_txn_id,subscriber_emailaddress,datecreation) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($ins2, 'ssssssssssssssssssssss', $subscr_id,$txn_type,$subscr_date,$subscr_effective,$period1,$period2,$period3,$amount1,$amount2,$amount3,$mc_amount1,$mc_amount2,$mc_amount3,$recurring,$reattempt,$retry_at,$recur_times,$username,$password,$txn_id,$payer_email,$fecha);
            mysqli_stmt_execute($ins2);
            mysqli_stmt_close($ins2);

            if($notify_email) mail($notify_email, "VERIFIED IPN", "$res\n $req\n $txn_type $subscr_id");
        }
    }

    // if the IPN POST was 'INVALID'...do this
    else if (strcmp($res, "INVALID") == 0) {
        // log for manual investigation
        if($notify_email) mail($notify_email, "INVALID IPN", "$res\n $req");
    }
}
fclose ($fp);
mysqli_close($Connect);
?>
