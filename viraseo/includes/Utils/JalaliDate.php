<?php
namespace ViraSEO\Utils;
defined('ABSPATH') || exit;

class JalaliDate {
    private static array $m = [1=>'فروردین',2=>'اردیبهشت',3=>'خرداد',4=>'تیر',5=>'مرداد',6=>'شهریور',7=>'مهر',8=>'آبان',9=>'آذر',10=>'دی',11=>'بهمن',12=>'اسفند'];

    public static function g2j(int $gy, int $gm, int $gd): array {
        $gdm=[0,31,59,90,120,151,181,212,243,273,304,334];
        $gy2=($gm>2)?($gy+1):$gy;
        $d=355666+(365*$gy)+intval(($gy2+3)/4)-intval(($gy2+99)/100)+intval(($gy2+399)/400)+$gd+$gdm[$gm-1];
        $jy=-1595+(33*intval($d/12053));$d%=12053;
        $jy+=4*intval($d/1461);$d%=1461;
        if($d>365){$jy+=intval(($d-1)/365);$d=($d-1)%365;}
        if($d<186){$jm=1+intval($d/31);$jd=1+($d%31);}
        else{$jm=7+intval(($d-186)/30);$jd=1+(($d-186)%30);}
        return['y'=>$jy,'m'=>$jm,'d'=>$jd,'date'=>sprintf('%04d/%02d/%02d',$jy,$jm,$jd)];
    }

    public static function j2g(int $jy, int $jm, int $jd): array {
        $jy+=1595;
        $d=-355668+(365*$jy)+(intval($jy/33)*8)+intval((($jy%33)+3)/4)+$jd+(($jm<7)?($jm-1)*31:(($jm-7)*30)+186);
        $gy=400*intval($d/146097);$d%=146097;
        if($d>36524){$gy+=100*intval(--$d/36524);$d%=36524;if($d>=365)$d++;}
        $gy+=4*intval($d/1461);$d%=1461;
        if($d>365){$gy+=intval(($d-1)/365);$d=($d-1)%365;}
        $gd=$d+1;$sa=[0,31,((($gy%4==0&&$gy%100!=0)||($gy%400==0))?29:28),31,30,31,30,31,31,30,31,30,31];
        $gm=0;for($i=1;$i<=12&&$gd>$sa[$i];$i++){$gd-=$sa[$i];$gm=$i;}$gm++;
        return['y'=>$gy,'m'=>$gm,'d'=>(int)$gd,'date'=>sprintf('%04d-%02d-%02d',$gy,$gm,$gd)];
    }

    public static function now(): array { return self::g2j((int)date('Y'),(int)date('m'),(int)date('d')); }
    public static function now_str(): string { return self::now()['date'].' '.date('H:i'); }

    public static function to_fa(string|int|float $s): string {
        return str_replace(['0','1','2','3','4','5','6','7','8','9'],['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],(string)$s);
    }
    public static function to_en(string $s): string {
        return str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],['0','1','2','3','4','5','6','7','8','9'],$s);
    }

    public static function format(string $dt, string $fmt='date'): string {
        $ts = strtotime($dt);
        if (!$ts) return '';
        $j = self::g2j((int)date('Y',$ts),(int)date('m',$ts),(int)date('d',$ts));
        return match($fmt) {
            'datetime' => self::to_fa($j['date'].' '.date('H:i',$ts)),
            'long' => self::to_fa($j['d']).' '.self::$m[$j['m']].' '.self::to_fa($j['y']),
            'relative' => self::relative($ts),
            default => self::to_fa($j['date']),
        };
    }

    public static function relative(int $ts): string {
        $d = time()-$ts;
        if($d<60) return 'لحظاتی پیش';
        if($d<3600) return self::to_fa(intval($d/60)).' دقیقه پیش';
        if($d<86400) return self::to_fa(intval($d/3600)).' ساعت پیش';
        if($d<604800) return self::to_fa(intval($d/86400)).' روز پیش';
        return self::format(date('Y-m-d H:i:s',$ts),'date');
    }

    public static function jalali_to_gregorian_str(string $jdate): ?string {
        $jdate = self::to_en(trim($jdate));
        $p = preg_split('/[\/\-]/', $jdate);
        if (count($p)!==3) return null;
        $r = self::j2g((int)$p[0],(int)$p[1],(int)$p[2]);
        return $r['date'];
    }
}
