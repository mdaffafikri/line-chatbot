<?php
require __DIR__ . '/../vendor/autoload.php';
 
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
 
use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\SignatureValidator as SignatureValidator;
 
$pass_signature = true;
 
// set LINE channel_access_token and channel_secret
$channel_access_token = "5+oMmL+vz4uMA7DZeD5rmaHqnPUZyspQn7oJk1DK9J8I8W+dB0jfb4eqBvCCq+OhHJNDytHk4fim3Zyd4DqlrOXdhfUdhBCyZXuBKVqSW5KLIDvOci5wywjDU8/4mUQ+FesJWVDu/vGYOWBUjs5q+AdB04t89/1O/w1cDnyilFU=";
$channel_secret = "0b539cf92e63d86daa79654d9bed56a4";
 
// inisiasi objek bot
$httpClient = new CurlHTTPClient($channel_access_token);
$bot = new LINEBot($httpClient, ['channelSecret' => $channel_secret]);
 
$app = AppFactory::create();
$app->setBasePath("/public");
 
$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello World!");
    return $response;
});
 
// buat route untuk webhook
$app->post('/webhook', function (Request $request, Response $response) use ($channel_secret, $bot, $httpClient, $pass_signature) {
    // get request body and line signature header
    $body = $request->getBody();
    $signature = $request->getHeaderLine('HTTP_X_LINE_SIGNATURE');
 
    // log body and signature
    file_put_contents('php://stderr', 'Body: ' . $body);
 
    if ($pass_signature === false) {
        // is LINE_SIGNATURE exists in request header?
        if (empty($signature)) {
            return $response->withStatus(400, 'Signature not set');
        }
 
        // is this request comes from LINE?
        if (!SignatureValidator::validateSignature($body, $channel_secret, $signature)) {
            return $response->withStatus(400, 'Invalid signature');
        }
    }
    
// kode aplikasi nanti disini

    $data = json_decode($body, true);
    if(is_array($data['events'])){
        foreach ($data['events'] as $event)
        {
            if ($event['type'] == 'message')
            {                                    
                $replyToken = $event['replyToken'];
                $specialMsg = strtolower($event['message']['text']);

                if($specialMsg == 'halo'){
                    $result = $bot->replyText($event['replyToken'], 'Hai');
                    
                    $response->getBody()->write(json_encode($result->getJSONDecodedBody()));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($result->getHTTPStatus());
                }

                if($specialMsg == 'apa kabar?'){
                    $textMessageBuilder = new TextMessageBuilder('Baik!');
                    $stickerMessageBuilder = new StickerMessageBuilder(11538, 51626501);

                    $multiMessageBuilder = new MultiMessageBuilder();
                    $multiMessageBuilder->add($textMessageBuilder);
                    $multiMessageBuilder->add($stickerMessageBuilder);

                    $result = $bot->replyMessage($replyToken, $multiMessageBuilder);

                    $response->getBody()->write(json_encode($result->getJSONDecodedBody()));
                    return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus($result->getHTTPStatus());
                }

                if($event['message']['type'] == 'sticker'){
                    $stickerMessageBuilder = new StickerMessageBuilder(1, 3);
                    $result = $bot->replyMessage($event['replyToken'], $stickerMessageBuilder);
                    
                    $response->getBody()->write(json_encode($result->getJSONDecodedBody()));
                    return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus($result->getHTTPStatus());
                }
                

                if($event['message']['type'] == 'text')
                {
                    // send same message as reply to user
                    $result = $bot->replyText($event['replyToken'], $event['message']['text']);                    

                    // or we can use replyMessage() instead to send reply message
                    // $textMessageBuilder = new TextMessageBuilder($event['message']['text']);
                    // $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);


                    $response->getBody()->write(json_encode($result->getJSONDecodedBody()));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($result->getHTTPStatus());
                }

                elseif(
                    $event['message']['type'] == 'image' or
                    $event['message']['type'] == 'video' or
                    $event['message']['type'] == 'audio' or
                    $event['message']['type'] == 'file'
                ){
                    $contentURL = " https://spearow.herokuapp.com/public/content/" . $event['message']['id'];
                    $contentType = ucfirst($event['message']['type']);
                    $result = $bot->replyText($event['replyToken'],
                        $contentType . " yang Anda kirim bisa diakses dari link:\n " . $contentURL);
                    $response->getBody()->write(json_encode($result->getJSONDecodedBody()));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($result->getHTTPStatus());
                }
            }
        }
        return $response->withStatus(200, 'for Webhook!'); //buat ngasih response 200 ke pas verify webhook
    }
    return $response->withStatus(400, 'No event sent!');
 
});

$app->get('/pushmessage', function ($req, $response) use ($bot) {
    // send push message to user
    $userId = 'U549e297d91e9f6834ece8711914b0564';
    $textMessageBuilder = new TextMessageBuilder('Your bot sent you a push message');
    $result = $bot->pushMessage($userId, $textMessageBuilder);
 
    $response->getBody()->write("Pesan push berhasil dikirim!");
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($result->getHTTPStatus());
});

$app->get('/profile', function ($req, $response) use ($bot)
{
    // get user profile
    $userId = 'U549e297d91e9f6834ece8711914b0564';
    $result = $bot->getProfile($userId);
 
    $response->getBody()->write(json_encode($result->getJSONDecodedBody()));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($result->getHTTPStatus());
});

$app->get('/content/{messageId}', function ($req, $response, $args) use ($bot) {
    // get message content
    $messageId = $args['messageId'];
    $result = $bot->getMessageContent($messageId);
    // set response
    $response->getBody()->write($result->getRawBody());
    return $response
        ->withHeader('Content-Type', $result->getHeader('Content-Type'))
        ->withStatus($result->getHTTPStatus());
});

$app->run();