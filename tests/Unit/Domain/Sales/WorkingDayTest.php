<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Domain\Sales\WorkingDay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkingDayTest extends TestCase
{
    private const string EASTER_AFTER_MARCH_21 = 'UL`QI\MaYI]UFYQBVMaRJ]UFZQ^VNaRJ^NFZK^VG[RJWObZK_VG[SCWOcSK_PG[L`WO\TKXPH[L`XH\TEXPdUL`QI\MEYP]UM`QI]MaYJ]UFZQBVNaRJ^VG[SCWOcSK_PG[L`WH\TKXPH[L`XH\TEXPdUL`QI\MEYP]UMYQIVMaYJ]UFZQBVNaRJ^NFZK^VN[RJ^OFZK_VG[SCWOcSK_PG[SDXP]ULYQI\MaYI]UFYQBVMaRJ]NFZQ^VNaRJ^NFZK^VG[RCWObSK_VG[SCWOcSK_PG[L`WH\TKXPH[L`XH\TEXPdUL`QI\MEYP]UMZRJWNbZK^VG[RCWObSK_OG[L_WO\SK_PG[L`WH\TDXPdTL`QH\TEXP]UL`QI\MaYI]UFYQIVMaYJ]UFZQ^VNaRJ^NFZK^VN[RJWObZK_VG[SCWOcSK_PG[L`WO\TK_PH[L`XH\TEXPdUL`QI\TEYP]UM`QI]MaYJ]UFZQIVNaYJ^UFZR^VNbRJ^OFZK_VN[SJWOcZK_WH\TEXP]UL`QI\MaYI]UFYQIVMaYJ]UFZQ^VNaRJ^NFZK^VN[RJWObZK_VG[SCWOcSK_PG[L`WO\TK_PH[L`XH\TEXPdUL`QI\TEYQ^VNaRJ^NFZK^VG[RJWObZK_VG[SCWOcSK_PG[L`WO\TKXPH[L`XH\TEXPdUL`QI\MEYP]UM`QI]MaYJ]UFZQBVNaRJ^UFZR^VN[SK_PG[L`WH\TKXPH[L`QH\TEXPdUL`QI\MEYP]UFYQIVMaYJ]UFZQBVNaRJ^NFZK^VN[RJ^OFZK_VG[SCWOcSK_PG[SDWO\TK_PH[L`XH\TEXPdUL`QI\MEYP]UM`QI]MaYJ]UFZQBVNaRJ^UFZR^VN[RJ^OFZK_VG[SJWOcZK_PG[SDWOcTK_PH[L`XO\TEXPHUL`XI]UFZQBVNaRJ^NFZK^VN[RJ^OFZK_VG[SCWOcSK_PG[SDWO\TK_PH[L`XH\TEXPHUL`QI\TEYPdUM`QI]MEYJ]UFZQIVNaYJ^UFZRCWOcSK_PG[L`WO\TK_PH[L`XH\TEXPdUL`QI\TEYP]UM`QI]MaYJ]UFZQIVNaRJ^UFZR^VNbRJ^OFZK_VG[SJWOcZK_WG[SDWOcTL`QI\MaYI]UFYQIVMaRJ]UFZQ^VNaRJ^NFZK^VG[RJWObZK_VG[SCWOcSK_PG[L`WO\TK_PH[L`XH\TEXPdUL`QI\TEYP]UM`QI]MaYJ]UFZQIVNaRJ^UFZR^VNbRJ^OFZK_VG[SJWOcZK_WG[SDWOcTK_PH[L`XO\TL_PH\L`XI\TEYPdUM`QI]TEYQ]UMZQI]NaYJ^VG[SJWOcSK_PG[SDWOcTK_PH[L`XH\TEXPHUL`XI\TEYPdUM`QI]MEYJ]UMZQI]NaYJ^UFZRBVNbRJ^OFZR_VN[SJ^OGZK_WG[SDXPdUL`QI\MEYP]UM`QI]MaYJ]UFZQBVNaRJ^UFZR^VN[RJ^OFZK_VG[SJWOcSK_PG[SDWOcTK_PH[L`XH\TEXPHUL`XI\TEYPdUMaRJ^NFZK^VN[RJ^ObZK_VG[SCWOcSK_PG[S`WO\TK_PH[L`XH\TEXPdUL`QI\TEYP]UM`QI]MaYJ]UFZQIVNaYJ^UFZR^VNbRJ^OFZK_VN[SJ^OcZK_WG[SDWOcTK_PH[S`XO\TL_PH\L`XI\TEYPdUM`QI]TEYQ]UMaQI]NaYJ^UFZRIVNbYJ^VFZR_VNbSJ^OGZK_WH\TEXPdUL`QI\TEYP]UM`QI]MaYJ]UFZQIVNaYJ^UFZR^VNbRJ^OFZK_VN[SJ^OcZK_WG[SDWOcTK_PH[S`XO\TL_PH\L`XI\TEYQBVNaRJ^UFZR^VNbRJ^OFZK_VG[SJWOcZK_WG[SDWOcTK_PH[L`XO\TL_PHUL`XI\TEYPdUM`QI]TEYJ]UMZQI]NaYJ^UFZRBVNbSK_PG[SDWOcTK_PH[L`XH\TEXPHUL`XI\TEYPdUM`QI]MEYJ]UMZQIVNaYJ^UFZRBVNbRJ^OFZK_VN[SJ^OGZK_WG[SDWOcTK_PH[L`XO\TL_PHUL`XI\TEYPdUM`QI]TEYJ]UMZQI]NaYJ^UFZRBVNbRJ^OFZR_VNbSJ^OGZK_WG[SDWOcTK_WH[SDXOcTL_PH\L`XI]UMZQIVNaYJ^UFZRBVNbRJ^OFZK_VN[SJ^OGZK_WG[SDWOcTK_PH[SDXOcTL_PH\L`XI\TEYPHUM`XI]TEYQdUMaQI]NEYJ^UMZRJWOcZK_WG[SDWOcTK_PH[L`XO\TL_PH\L`XI\TEYPdUM`QI]TEYQ]UMaQI]NaYJ^UFZRIVNbYJ^VFZR_VNbSJ^OGZK_WN[SDWOcTL`QI\TEYP]UM`QI]MaYJ]UFZQIVNaYJ^UFZR^VNbRJ^OFZK_VN[SJWOcZK_WG[SDWOcTK_PH[L`XO\TL_PH\L`XI\TEYPdUM`QI]TEYQ]UMaQI]NaYJ^UFZRIVNbYJ^VFZR_VNbSJ^OGZK_WN[SDWOcTK_WH[SDXOcTL_PH\L`XI\TLYPH\M`XI]TEYQdUMaQI]NEYQ^';

    /** @return iterable<string, array{string, string}> */
    public static function days(): iterable
    {
        yield 'a Tuesday' => ['2026-11-10', '2026-11-10'];
        yield 'a Friday' => ['2026-11-13', '2026-11-13'];
        yield 'a Saturday' => ['2026-11-14', '2026-11-16'];
        yield 'a Sunday' => ['2026-11-15', '2026-11-16'];
        yield 'New Year on a Thursday' => ['2026-01-01', '2026-01-02'];
        yield 'Labour Day on a Friday' => ['2026-05-01', '2026-05-04'];
        yield 'Victory Day on a Friday' => ['2026-05-08', '2026-05-11'];
        yield 'Bastille Day' => ['2026-07-14', '2026-07-15'];
        yield 'Assumption on a Saturday' => ['2026-08-15', '2026-08-17'];
        yield 'All Saints on a Sunday' => ['2026-11-01', '2026-11-02'];
        yield 'Armistice Day' => ['2026-11-11', '2026-11-12'];
        yield 'Christmas then the weekend' => ['2026-12-25', '2026-12-28'];
        yield 'New Year 2027 then the weekend' => ['2027-01-01', '2027-01-04'];
        yield 'Ascension' => ['2026-05-14', '2026-05-15'];
        yield 'Whit Monday' => ['2026-05-25', '2026-05-26'];
        yield 'Ascension on Labour Day' => ['2008-05-01', '2008-05-02'];
        yield 'Good Friday, then the Easter weekend and Monday' => ['2026-04-03', '2026-04-07'];
        yield 'the day before Good Friday' => ['2026-04-02', '2026-04-02'];
        yield 'St Stephen on a Friday' => ['2025-12-26', '2025-12-29'];
        yield 'St Stephen on a Tuesday' => ['2028-12-26', '2028-12-27'];
        yield 'the day before Ascension' => ['2026-05-13', '2026-05-13'];
    }

    #[DataProvider('days')]
    public function testTheFirstWorkingDayOnOrAfterADay(string $day, string $expected): void
    {
        $workingDay = WorkingDay::from(new \DateTimeImmutable($day.' 00:00:00 UTC'));

        self::assertSame($expected.'T00:00:00+00:00', $workingDay->format(\DATE_ATOM));
    }

    /** @return iterable<string, array{string}> */
    public static function easters(): iterable
    {
        yield '1761' => ['1761-03-22'];
        yield '1800' => ['1800-04-13'];
        yield '1818' => ['1818-03-22'];
        yield '1900' => ['1900-04-15'];
        yield '1943' => ['1943-04-25'];
        yield '1954' => ['1954-04-18'];
        yield '1961' => ['1961-04-02'];
        yield '1981' => ['1981-04-19'];
        yield '2000' => ['2000-04-23'];
        yield '2008' => ['2008-03-23'];
        yield '2011' => ['2011-04-24'];
        yield '2019' => ['2019-04-21'];
        yield '2024' => ['2024-03-31'];
        yield '2025' => ['2025-04-20'];
        yield '2026' => ['2026-04-05'];
        yield '2027' => ['2027-03-28'];
        yield '2038' => ['2038-04-25'];
        yield '2049' => ['2049-04-18'];
        yield '2100' => ['2100-03-28'];
        yield '2160' => ['2160-03-23'];
        yield '2200' => ['2200-04-06'];
        yield '2285' => ['2285-03-22'];
        yield '2300' => ['2300-04-08'];
        yield '2400' => ['2400-04-16'];
        yield '2500' => ['2500-04-18'];
        yield '2600' => ['2600-03-23'];
        yield '2700' => ['2700-04-01'];
        yield '2900' => ['2900-04-11'];
        yield '3000' => ['3000-04-13'];
        yield '4099' => ['4099-04-19'];
    }

    #[DataProvider('easters')]
    public function testEasterMondayIsOffEveryYear(string $easter): void
    {
        $sunday = new \DateTimeImmutable($easter.' 00:00:00 UTC');

        self::assertEquals($sunday->modify('+2 days'), WorkingDay::from($sunday->modify('+1 day')));
        self::assertEquals($sunday->modify('+2 days'), WorkingDay::from($sunday->modify('-2 days')));
        self::assertEquals($sunday->modify('-3 days'), WorkingDay::from($sunday->modify('-3 days')));
        self::assertEquals(WorkingDay::from($sunday->modify('+40 days')), WorkingDay::from($sunday->modify('+39 days')));
        self::assertEquals($sunday->modify('+51 days'), WorkingDay::from($sunday->modify('+50 days')));
    }

    public function testTheMovableHolidaysFollowEasterFrom1583To4099(): void
    {
        foreach (str_split(self::EASTER_AFTER_MARCH_21) as $index => $offset) {
            $sunday = new \DateTimeImmutable((1583 + $index).'-03-21 00:00:00 UTC')->add(new \DateInterval('P'.(\ord($offset) - \ord('A')).'D'));

            self::assertEquals($sunday->modify('+2 days'), WorkingDay::from($sunday->modify('+1 day')), 'Easter Monday '.$sunday->format('Y'));
            self::assertEquals($sunday->modify('+2 days'), WorkingDay::from($sunday->modify('-2 days')), 'Good Friday '.$sunday->format('Y'));
            self::assertEquals(WorkingDay::from($sunday->modify('+40 days')), WorkingDay::from($sunday->modify('+39 days')), 'Ascension '.$sunday->format('Y'));
            self::assertEquals($sunday->modify('+51 days'), WorkingDay::from($sunday->modify('+50 days')), 'Whit Monday '.$sunday->format('Y'));
        }
    }
}
