<?php

$ch = curl_init('http://127.0.0.1:8000/api/frontend-admin/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['email'=>'admin@theafricanmail.com','password'=>'Admin@1234']));
curl_setopt($ch, CURLOPT_HEADER, true);
$res = curl_exec($ch);
if (!$res) {
    echo 'CURL ERROR';
    exit(1);
}
echo $res;
curl_close($ch);
