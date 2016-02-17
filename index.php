<?php
/**
 * GETメソッドのリクエスト [ベアラートークン]
 * https://syncer.jp/twitter-api-matome
 * 2015-09-01
 */

// 設定
$bearer_token = '' ;    // ベアラートークン
$request_url = 'https://api.twitter.com/1.1/statuses/user_timeline.json' ;      // エンドポイント

// パラメータ
$params = array(
    'screen_name' => '@nojima_tsuyoshi' ,
    'count' => 10 ,
) ;

// パラメータがある場合
if( $params )
{
    $request_url .= '?' . http_build_query( $params ) ;
}

// リクエスト用のコンテキスト
$context = array(
    'http' => array(
        'method' => 'GET' , // リクエストメソッド
        'header' => array(            // ヘッダー
            'Authorization: Bearer ' . $bearer_token ,
        ) ,
    ) ,
) ;

// cURLを使ってリクエスト
$curl = curl_init() ;
curl_setopt( $curl , CURLOPT_URL , $request_url ) ;
curl_setopt( $curl , CURLOPT_HEADER, 1 ) ;
curl_setopt( $curl , CURLOPT_CUSTOMREQUEST , $context['http']['method'] ) ;         // メソッド
curl_setopt( $curl , CURLOPT_SSL_VERIFYPEER , false ) ;                             // 証明書の検証を行わない
curl_setopt( $curl , CURLOPT_RETURNTRANSFER , true ) ;                              // curl_execの結果を文字列で返す
curl_setopt( $curl , CURLOPT_HTTPHEADER , $context['http']['header'] ) ;            // ヘッダー
curl_setopt( $curl , CURLOPT_TIMEOUT , 5 ) ;                                        // タイムアウトの秒数
$res1 = curl_exec( $curl ) ;
$res2 = curl_getinfo( $curl ) ;
curl_close( $curl ) ;

// 取得したデータ
$json = substr( $res1, $res2['header_size'] ) ;             // 取得したデータ(JSONなど)
$header = substr( $res1, 0, $res2['header_size'] ) ;        // レスポンスヘッダー (検証に利用したい場合にどうぞ)

// [cURL]ではなく、[file_get_contents()]を使うには下記の通りです…
// $json = @file_get_contents( $request_url , false , stream_context_create( $context ) ) ;

// JSONをオブジェクトに変換
$obj = json_decode( $json ) ;

// HTML用
$html = '' ;

// エラー判定
if( !$json || !$obj )
{
    $html .= '<h2>エラー内容</h2>' ;
    $html .= '<p>データを取得することができませんでした…。設定を見直して下さい。</p>' ;
}

// 検証用にレスポンスヘッダーを出力 [本番環境では不要]
$html .= '<h2>取得したデータ</h2>' ;
$html .= '<p>下記のデータを取得できました。</p>' ;
$html .=    '<h3>ボディ(JSON)</h3>' ;
$html .=    '<p><textarea rows="8">' . $json . '</textarea></p>' ;
$html .=    '<h3>レスポンスヘッダー</h3>' ;
$html .=    '<p><textarea rows="8">' . $header . '</textarea></p>' ;

/**
 * substr_replace をマルチバイト文字列に対応
 * https://gist.github.com/stemar/8287074
 * 2015-02-17
 */
function mb_substr_replace($string, $replacement, $start, $length=NULL) {
    if (is_array($string)) {
        $num = count($string);
        // $replacement
        $replacement = is_array($replacement) ? array_slice($replacement, 0, $num) : array_pad(array($replacement), $num, $replacement);
        // $start
        if (is_array($start)) {
            $start = array_slice($start, 0, $num);
            foreach ($start as $key => $value)
                $start[$key] = is_int($value) ? $value : 0;
        }
        else {
            $start = array_pad(array($start), $num, $start);
        }
        // $length
        if (!isset($length)) {
            $length = array_fill(0, $num, 0);
        }
        elseif (is_array($length)) {
            $length = array_slice($length, 0, $num);
            foreach ($length as $key => $value)
                $length[$key] = isset($value) ? (is_int($value) ? $value : $num) : 0;
        }
        else {
            $length = array_pad(array($length), $num, $length);
        }
        // Recursive call
        return array_map(__FUNCTION__, $string, $replacement, $start, $length);
    }
    preg_match_all('/./us', (string)$string, $smatches);
    preg_match_all('/./us', (string)$replacement, $rmatches);
    if ($length === NULL) $length = mb_strlen($string);
    array_splice($smatches[0], $start, $length, $rmatches[0]);
    return join($smatches[0]);
}

/**
 * Twitter APIから取得したツイート情報のurls、user_mentions、hashtagsをリンクに変換
 * http://stackoverflow.com/questions/11533214/php-how-to-use-the-twitter-apis-data-to-convert-urls-mentions-and-hastags-in
 * 2014-08-26
 */
function json_tweet_text_to_HTML($tweet, $links=true, $users=true, $hashtags=true)
{
    $return = $tweet->text;
    // echo $tweet->text;

    $entities = array();

    if($links && is_array($tweet->entities->urls))
    {
        foreach($tweet->entities->urls as $e)
        {
            $temp["start"] = $e->indices[0];
            $temp["end"] = $e->indices[1];
            $temp["replacement"] = "<a href='".$e->expanded_url."' target='_blank'>".$e->display_url."</a>";
            $entities[] = $temp;
        }
    }
    if($users && is_array($tweet->entities->user_mentions))
    {
        foreach($tweet->entities->user_mentions as $e)
        {
            $temp["start"] = $e->indices[0];
            $temp["end"] = $e->indices[1];
            $temp["replacement"] = "<a href='https://twitter.com/".$e->screen_name."' target='_blank'>@".$e->screen_name."</a>";
            $entities[] = $temp;
        }
    }
    if($hashtags && is_array($tweet->entities->hashtags))
    {
        foreach($tweet->entities->hashtags as $e)
        {
            $temp["start"] = $e->indices[0];
            $temp["end"] = $e->indices[1];
            $temp["replacement"] = "<a href='https://twitter.com/hashtag/".$e->text."?src=hash' target='_blank'>#".$e->text."</a>";
            $entities[] = $temp;
        }
    }

    usort($entities, function($a,$b){return($b["start"]-$a["start"]);});


    foreach($entities as $item)
    {
        //$return = substr_replace($return, $item["replacement"], $item["start"], $item["end"] - $item["start"]);
        $return = mb_substr_replace($return, $item["replacement"], $item["start"], $item["end"] - $item["start"]);
    }

    return($return);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Twitter API 1.1 ベアラートークンによるフィード取得</title>
    <script type="text/javascript">
        var timerStart = Date.now();
    </script>
</head>
<body>
<h1>Twitter API 1.1 ベアラートークンによるフィード取得</h1>
<?php echo $html; ?>
<h2>出力サンプル</h2>
<ul>
<?php
    foreach ($obj as $key => $value) {
        echo '<li>';
        //投稿日時
        $created_at = strtotime($value->created_at);
        $created_at = date('Y/m/d', $created_at);

        $text = json_tweet_text_to_HTML($value);
        echo $created_at . ' ' . $text . '<br>';
        echo '</li>';
    }
?>
</ul>
<h2>オブジェクト情報</h2>
<pre>
<?php print_r($obj); ?>
</pre>
</body>
</html>
