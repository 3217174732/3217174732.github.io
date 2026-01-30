<?php
/**
 * PHP后端请求转发核心文件
 * 功能：接收前端参数 → CURL转发GET请求 → 返回响应给前端
 * 解决：浏览器CORS跨域限制，后端CURL无跨域问题
 */
header("Content-Type: application/json; charset=UTF-8");

// ########## 1. 处理跨域CORS（关键，前端调PHP后端必配）##########
// 允许所有源跨域（开发环境，生产环境可指定前端域名如http://localhost）
header("Access-Control-Allow-Origin: *");
// 允许的请求方法
header("Access-Control-Allow-Methods: POST, OPTIONS");
// 允许的请求头
header("Access-Control-Allow-Headers: Content-Type");
// 预检请求（OPTIONS）直接返回200，无需处理业务
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ########## 2. 接收前端POST传递的JSON参数 ##########
$rawInput = file_get_contents('php://input');
$params = json_decode($rawInput, true);
// 校验参数是否有效
if (!is_array($params) || empty($params['cookie']) || empty($params['code'])) {
    echo json_encode([
        'code' => -1,
        'msg' => '参数错误：Cookie和code不能为空',
        'statusCode' => '无',
        'responseText' => ''
    ]);
    exit;
}
$cookie = $params['cookie'];   // 前端传递的Cookie
$code = $params['code'];       // 前端传递的code参数

// ########## 3. 固定配置（和原请求一致，仅code可改）##########
$BASE_URL = 'https://openapi.52vmy.cn/post/user/2026jika';
$fixedAct = 'receive_card';
$fixedUser = '3217174732';
// 拼接GET请求URL（带参数）
$requestUrl = $BASE_URL . '?act=' . urlencode($fixedAct) . '&user=' . urlencode($fixedUser) . '&code=' . urlencode($code);

// ########## 4. 初始化CURL（后端转发请求核心，无CORS限制）##########
$curl = curl_init();
// CURL核心配置
curl_setopt_array($curl, [
    CURLOPT_URL => $requestUrl,                // 目标请求地址
    CURLOPT_RETURNTRANSFER => true,            // 执行后返回结果，不直接输出
    CURLOPT_FOLLOWLOCATION => true,            // 跟随重定向
    CURLOPT_TIMEOUT => 20,                     // 超时时间20秒
    CURLOPT_SSL_VERIFYPEER => false,           // 关闭SSL证书验证（目标是HTTPS，开发环境忽略）
    CURLOPT_SSL_VERIFYHOST => false,           // 关闭SSL主机验证
    // 设置请求头（和原请求完全一致，确保身份验证/来源校验通过）
    CURLOPT_HTTPHEADER => [
        'Cookie: ' . $cookie,
        'Referer: https://openapi.52vmy.cn/user/2026StarCard',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*'
    ]
]);

// ########## 5. 执行CURL请求，获取响应 ##########
$responseText = curl_exec($curl);
$statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE); // 获取目标接口响应状态码
$curlError = curl_error($curl);
curl_close($curl); // 关闭CURL

// ########## 6. 处理响应，返回给前端 ##########
if ($curlError) {
    // CURL执行失败（如网络错误）
    $result = [
        'code' => -2,
        'msg' => '请求转发失败：' . $curlError,
        'statusCode' => '无',
        'responseText' => ''
    ];
} else {
    // 转发成功，返回目标接口的状态码和响应内容
    $result = [
        'code' => 200,
        'msg' => '请求成功',
        'statusCode' => $statusCode,
        'responseText' => $responseText ?: '无响应内容'
    ];
}

// 输出JSON结果给前端
echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;
?>
