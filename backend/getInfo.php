<?php

include("country.php");
include("qqwry.php");
include("ipinfo.php");
include("ipip.php");
include("cityCN.php");
include("specialIP.php");

function getIPInfo($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $ip = long2ip6(ip2long6($ip)); // 压缩IPv6地址
    }
    $specialInfo = checkSpecial($ip); // 检查是否为特殊IP段
    if ($specialInfo !== null) {
        $info['ip'] = $ip;
        $info['as'] = null;
        $info['city'] = null;
        $info['region'] = null;
        $info['country'] = null;
        $info['timezone'] = null;
        $info['loc'] = null;
        $info['isp'] = $specialInfo['descEn'];
        $info['scope'] = $specialInfo['scope'];
        $info['detail'] = $specialInfo['descCn'];
    } else {
        $IPIP = new IPDB('ipipfree.ipdb');
        $addr = $IPIP->getDistrict($ip); // 获取IPIP.net数据
        $data = IPinfo::getInfo($ip); // 获取ipinfo.io数据
        $country = getCountry($data['country']); // 解析国家2位编码
        $qqwry = new QQWry('qqwry.dat');
        $detail = $qqwry->getDetail($ip); // 获取纯真IP数据
        $info['ip'] = $data['ip'];
        $info['as'] = $data['as'];
        $info['city'] = $data['city'];
        $info['region'] = $data['region'];
        if (isset($data['country'])) {
            $info['country'] = $data['country'];
            if (isset($country['en'])) {
                $info['country'] .= ' - ' . $country['en'];
                $info['country'] .= "（" . $country['cn'] . "）";
            }
        }
        $info['timezone'] = $data['timezone'];
        $info['loc'] = $data['loc'];
        $info['isp'] = $data['isp'];
        if ($detail['country'] == '中国') {
            $info['country'] = 'CN - China（中国）';
            $info['timezone'] = 'Asia/Shanghai';
            if ($detail['region'] == '台湾') { // 修正台湾数据带 "市" 或 "县" 的情况
                if (mb_substr($detail['city'], -1) == '市' || mb_substr($detail['city'], -1) == '县') {
                    $detail['city'] = mb_substr($detail['city'], 0, mb_strlen($detail['city']) - 1);
                }
            }
            if ($detail['region'] == '' && $detail['city'] == '') { // 纯真库解析不出数据
                if ($addr[1] != '' || $addr[2] != '') { // IPIP数据不同时为空
                    $detail['region'] = $addr[1];
                    $detail['city'] = $addr[2];
                }
            } else if ($detail['region'] == '' || $detail['city'] == '') { // 纯真库存在空数据
                if ($addr[1] != '' && $addr[2] != '') { // IPIP数据完整
                    $detail['region'] = $addr[1]; // 修正纯真数据
                    $detail['city'] = $addr[2];
                }
            }
            if ($detail['region'] != '' || $detail['city'] != '') { // 修正后数据不同时为空
                $cityLoc = getLoc($detail['region'], $detail['city']); // 获取城市经纬度
                if ($cityLoc['region'] != '香港' && $cityLoc['region'] != '澳门' && $cityLoc['region'] != '台湾') { // 跳过港澳台数据
                    $info['region'] = $cityLoc['region'];
                    $info['city'] = $cityLoc['city'];
                    $info['loc'] = $cityLoc['lat'] . ',' . $cityLoc['lon'];
                }
            }
            if ($detail['isp'] == '教育网') { // 载入纯真库分析出的ISP数据
                $info['isp'] = 'China Education and Research Network';
            } else if ($detail['isp'] == '电信') {
                $info['isp'] = 'China Telecom';
            } else if ($detail['isp'] == '联通') {
                $info['isp'] = 'China Unicom Limited';
            } else if ($detail['isp'] == '移动') {
                $info['isp'] = 'China Mobile Communications Corporation';
            } else if ($detail['isp'] == '铁通') {
                $info['isp'] = 'China Tietong Telecom';
            } else if ($detail['isp'] == '广电网') {
                $info['isp'] = 'Shaanxi Broadcast & TV Network Intermediary';
            } else if ($detail['isp'] == '鹏博士') {
                $info['isp'] = 'Chengdu Dr.Peng Technology';
            } else if ($detail['isp'] == '长城') {
                $info['isp'] = 'Great Wall Broadband Network Service';
            } else if ($detail['isp'] == '中华电信') {
                $info['isp'] = 'ChungHwa Telecom';
            } else if ($detail['isp'] == '亚太电信') {
                $info['isp'] = 'Asia Pacific Telecom';
            } else if ($detail['isp'] == '远传电信') {
                $info['isp'] = 'Far EasTone Telecommunications';
            }
        }
        if (filter_var($ip, \FILTER_VALIDATE_IP,\FILTER_FLAG_IPV4)) { // 录入纯真库数据
            $info['scope'] = tryCIDR($detail['beginIP'], $detail['endIP']);
            $info['detail'] = $detail['dataA'] . $detail['dataB'];
        } else {
            $info['scope'] = $info['ip'];
            $info['detail'] = $info['as'] . ' ' . $info['isp'];
        }
    }
    if (trim($info['detail']) == '') {
        $info['detail'] = null;
    }
    return $info;
}

function tryCIDR($beginIP, $endIP) { // 给定IP范围，尝试计算CIDR
    $tmp = ip2long($endIP) - ip2long($beginIP) + 1;
    if (pow(2, intval(log($tmp, 2))) == $tmp) { // 判断是否为2的整数次方
        return $beginIP . '/' . (32 - log($tmp, 2));
    } else {
        return $beginIP . ' - ' . $endIP;
    }
}

function getVersion() { // 获取自身及数据库版本号
    global $myVersion;
    $version['echoip'] = $myVersion;
    $qqwry = new QQWry('qqwry.dat');
    $IPIP = new IPDB('ipipfree.ipdb');
    $version['qqwry'] = $qqwry->getVersion();
    $version['ipip'] = $IPIP->getVersion();
    return $version;
}

?>
