<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * Real marques, models and engines.
 *
 * The generated version produced "Series 1" through "Series 10" for every make with
 * engines named at random, which made every filter result indistinguishable from every
 * other — you could not tell by looking whether a filter had worked at all. Recognisable
 * data is not decoration; it is what makes the shop testable by eye.
 *
 * Weighted toward what actually drives here: German marques, French and Italian small
 * cars, and the diesel estates and vans that make up most of the parts trade.
 *
 * Kept in this directory rather than a Data/ subfolder on purpose — Docker Desktop's
 * bind mount does not surface newly created DIRECTORIES to the container, so a new
 * subfolder is invisible until the container is recreated.
 */
final class VehicleData
{
    /**
     * make => model => [[sub-model, engine code, kW, year from, year to, fuel], ...]
     *
     * @return array<string, array<string, list<array{0: string, 1: string, 2: int, 3: int, 4: ?int, 5: string}>>>
     */
    public static function tree(): array
    {
        return [
            'Volkswagen' => [
                'Golf V' => [
                    ['1.9 TDI', 'BXE', 77, 2003, 2009, 'diesel'],
                    ['2.0 TDI', 'BKD', 103, 2003, 2009, 'diesel'],
                    ['1.4 TSI', 'BMY', 103, 2006, 2009, 'petrol'],
                ],
                'Golf VI' => [
                    ['1.6 TDI', 'CAYC', 77, 2009, 2013, 'diesel'],
                    ['2.0 TDI', 'CBAB', 103, 2008, 2013, 'diesel'],
                    ['1.4 TSI', 'CAXA', 90, 2008, 2013, 'petrol'],
                ],
                'Golf VII' => [
                    ['1.6 TDI', 'CLHA', 77, 2012, 2020, 'diesel'],
                    ['2.0 TDI', 'CRBC', 110, 2012, 2020, 'diesel'],
                    ['1.4 TSI', 'CZCA', 92, 2012, 2020, 'petrol'],
                ],
                'Passat B6' => [
                    ['2.0 TDI', 'BKP', 103, 2005, 2010, 'diesel'],
                    ['1.9 TDI', 'BLS', 77, 2005, 2010, 'diesel'],
                ],
                'Passat B7' => [
                    ['2.0 TDI', 'CFFB', 103, 2010, 2014, 'diesel'],
                    ['1.6 TDI', 'CAYC', 77, 2010, 2014, 'diesel'],
                ],
                'Polo IV' => [['1.4 TDI', 'BNM', 51, 2001, 2009, 'diesel']],
                'Caddy III' => [['1.9 TDI', 'BJB', 77, 2004, 2010, 'diesel']],
                'Tiguan I' => [['2.0 TDI', 'CBAB', 103, 2007, 2016, 'diesel']],
            ],
            'BMW' => [
                '3 Series E90' => [
                    ['320d', 'N47D20', 130, 2005, 2012, 'diesel'],
                    ['318i', 'N46B20', 105, 2005, 2012, 'petrol'],
                    ['330d', 'M57D30', 170, 2005, 2011, 'diesel'],
                ],
                '3 Series F30' => [
                    ['320d', 'N47D20', 135, 2011, 2018, 'diesel'],
                    ['318d', 'N47D20', 105, 2012, 2018, 'diesel'],
                ],
                '5 Series E60' => [
                    ['520d', 'M47D20', 120, 2003, 2010, 'diesel'],
                    ['530d', 'M57D30', 173, 2003, 2010, 'diesel'],
                ],
                'X3 E83' => [['2.0d', 'M47D20', 110, 2004, 2010, 'diesel']],
                '1 Series E87' => [['118d', 'N47D20', 105, 2004, 2011, 'diesel']],
            ],
            'Mercedes-Benz' => [
                'C-Class W204' => [
                    ['C 220 CDI', 'OM646', 125, 2007, 2014, 'diesel'],
                    ['C 200 CDI', 'OM651', 100, 2008, 2014, 'diesel'],
                ],
                'E-Class W211' => [
                    ['E 220 CDI', 'OM646', 110, 2002, 2009, 'diesel'],
                    ['E 320 CDI', 'OM648', 150, 2002, 2009, 'diesel'],
                ],
                'A-Class W169' => [['A 180 CDI', 'OM640', 80, 2004, 2012, 'diesel']],
                'Sprinter 906' => [
                    ['311 CDI', 'OM646', 80, 2006, 2018, 'diesel'],
                    ['313 CDI', 'OM651', 95, 2009, 2018, 'diesel'],
                ],
                'Vito W639' => [['111 CDI', 'OM646', 80, 2003, 2014, 'diesel']],
            ],
            'Audi' => [
                'A4 B7' => [
                    ['2.0 TDI', 'BRE', 103, 2004, 2008, 'diesel'],
                    ['1.9 TDI', 'BRB', 85, 2004, 2008, 'diesel'],
                ],
                'A4 B8' => [['2.0 TDI', 'CAGA', 105, 2007, 2015, 'diesel']],
                'A3 8P' => [['1.9 TDI', 'BKC', 77, 2003, 2012, 'diesel']],
                'A6 C6' => [['2.0 TDI', 'BRE', 103, 2004, 2011, 'diesel']],
            ],
            'Opel' => [
                'Astra H' => [
                    ['1.7 CDTI', 'Z17DTH', 74, 2004, 2010, 'diesel'],
                    ['1.6 16V', 'Z16XEP', 77, 2004, 2010, 'petrol'],
                ],
                'Astra J' => [['1.7 CDTI', 'A17DTS', 96, 2009, 2015, 'diesel']],
                'Corsa D' => [['1.3 CDTI', 'Z13DTJ', 55, 2006, 2014, 'diesel']],
                'Insignia A' => [['2.0 CDTI', 'A20DTH', 118, 2008, 2017, 'diesel']],
                'Zafira B' => [['1.9 CDTI', 'Z19DT', 88, 2005, 2014, 'diesel']],
            ],
            'Renault' => [
                'Clio III' => [['1.5 dCi', 'K9K', 63, 2005, 2012, 'diesel']],
                'Megane II' => [['1.5 dCi', 'K9K', 78, 2002, 2009, 'diesel']],
                'Megane III' => [['1.5 dCi', 'K9K', 81, 2008, 2016, 'diesel']],
                'Kangoo II' => [['1.5 dCi', 'K9K', 63, 2008, 2021, 'diesel']],
                'Trafic II' => [['2.0 dCi', 'M9R', 84, 2006, 2014, 'diesel']],
            ],
            'Peugeot' => [
                '207' => [['1.6 HDi', 'DV6TED4', 66, 2006, 2012, 'diesel']],
                '308 I' => [['1.6 HDi', 'DV6C', 82, 2007, 2013, 'diesel']],
                '3008 I' => [['1.6 HDi', 'DV6C', 82, 2009, 2016, 'diesel']],
                'Partner II' => [['1.6 HDi', 'DV6ATED4', 55, 2008, 2018, 'diesel']],
            ],
            'Skoda' => [
                'Octavia II' => [
                    ['1.9 TDI', 'BXE', 77, 2004, 2013, 'diesel'],
                    ['2.0 TDI', 'BKD', 103, 2004, 2013, 'diesel'],
                ],
                'Octavia III' => [['1.6 TDI', 'CLHA', 77, 2012, 2020, 'diesel']],
                'Fabia II' => [['1.4 TDI', 'BNM', 51, 2007, 2014, 'diesel']],
                'Superb II' => [['2.0 TDI', 'CFFB', 103, 2008, 2015, 'diesel']],
            ],
            'Ford' => [
                'Focus II' => [
                    ['1.6 TDCi', 'HHDA', 80, 2004, 2011, 'diesel'],
                    ['1.8 TDCi', 'KKDA', 85, 2005, 2011, 'diesel'],
                ],
                'Fiesta VI' => [['1.4 TDCi', 'F6JA', 50, 2008, 2017, 'diesel']],
                'Mondeo IV' => [['2.0 TDCi', 'QXBA', 103, 2007, 2014, 'diesel']],
                'Transit VI' => [['2.2 TDCi', 'QVFW', 81, 2006, 2014, 'diesel']],
            ],
            'Toyota' => [
                'Corolla E120' => [['2.0 D-4D', '1CD-FTV', 85, 2001, 2007, 'diesel']],
                'Yaris II' => [['1.4 D-4D', '1ND-TV', 66, 2005, 2011, 'diesel']],
                'Auris I' => [['1.4 D-4D', '1ND-TV', 66, 2006, 2012, 'diesel']],
                'RAV4 III' => [['2.2 D-4D', '2AD-FTV', 100, 2005, 2012, 'diesel']],
            ],
            'Fiat' => [
                'Punto II' => [['1.3 JTD', '188A9000', 51, 1999, 2010, 'diesel']],
                'Doblo I' => [['1.9 JTD', '223A7000', 77, 2001, 2010, 'diesel']],
                'Panda II' => [['1.2 8V', '169A4000', 44, 2003, 2012, 'petrol']],
            ],
            'Citroen' => [
                'C3 I' => [['1.4 HDi', 'DV4TD', 50, 2002, 2009, 'diesel']],
                'C4 I' => [['1.6 HDi', 'DV6TED4', 66, 2004, 2010, 'diesel']],
                'Berlingo II' => [['1.6 HDi', 'DV6ATED4', 55, 2008, 2018, 'diesel']],
            ],
            'Nissan' => [
                'Qashqai J10' => [['1.5 dCi', 'K9K', 78, 2007, 2013, 'diesel']],
                'Micra K12' => [['1.5 dCi', 'K9K', 60, 2003, 2010, 'diesel']],
            ],
            'Hyundai' => [
                'i30 FD' => [['1.6 CRDi', 'D4FB', 85, 2007, 2012, 'diesel']],
                'Tucson JM' => [['2.0 CRDi', 'D4EA', 83, 2004, 2010, 'diesel']],
            ],
            'Kia' => [
                'Ceed ED' => [['1.6 CRDi', 'D4FB', 85, 2006, 2012, 'diesel']],
                'Sportage JE' => [['2.0 CRDi', 'D4EA', 103, 2004, 2010, 'diesel']],
            ],
            'Dacia' => [
                'Sandero I' => [['1.5 dCi', 'K9K', 63, 2008, 2012, 'diesel']],
                'Duster I' => [['1.5 dCi', 'K9K', 79, 2010, 2017, 'diesel']],
                'Logan I' => [['1.5 dCi', 'K9K', 50, 2004, 2012, 'diesel']],
            ],
        ];
    }

    /** Real aftermarket brands — the ones a parts counter actually stocks. */
    public static function brands(): array
    {
        return [
            'Bosch', 'Mann-Filter', 'Febi Bilstein', 'SKF', 'Sachs', 'Brembo', 'Valeo',
            'Hella', 'NGK', 'Denso', 'Gates', 'Continental', 'TRW', 'Lemforder', 'Mahle',
            'Monroe', 'KYB', 'Textar', 'Ferodo', 'ATE', 'Delphi', 'Elring', 'Ruville',
            'Optimal', 'SWAG', 'Meyle', 'Bilstein', 'Corteco', 'Pierburg', 'Nissens',
            'LuK', 'INA', 'Dayco', 'SNR', 'Blue Print', 'Japanparts',
        ];
    }

    /**
     * Category slug => the part types actually sold under it, with a plausible price
     * band in minor units. Product names are built from brand + type + a real spec, so a
     * listing reads like a parts catalogue rather than lorem ipsum.
     *
     * @return array<string, list<array{0: string, 1: int, 2: int}>>
     */
    public static function partsByCategory(): array
    {
        return [
            'brake-discs' => [['Brake Disc Front', 180_000, 620_000], ['Brake Disc Rear', 150_000, 520_000]],
            'brake-pads' => [['Brake Pad Set Front', 120_000, 420_000], ['Brake Pad Set Rear', 110_000, 380_000]],
            'brake-calipers' => [['Brake Caliper Front', 450_000, 1_800_000]],
            'brake-fluid' => [['Brake Fluid DOT 4 1L', 35_000, 90_000]],
            'alloy-wheels' => [['Alloy Wheel 16"', 700_000, 1_900_000], ['Alloy Wheel 17"', 900_000, 2_400_000]],
            'tires' => [['Tyre 205/55 R16', 350_000, 900_000], ['Tyre 195/65 R15', 280_000, 700_000]],
            'wheel-nuts' => [['Wheel Nut Set', 40_000, 130_000]],
            'hub-caps' => [['Hub Cap Set 15"', 60_000, 180_000]],
            'timing-belts' => [['Timing Belt Kit', 380_000, 1_400_000]],
            'gaskets' => [['Cylinder Head Gasket', 120_000, 560_000], ['Valve Cover Gasket', 60_000, 220_000]],
            'pistons' => [['Piston Set', 900_000, 3_200_000]],
            'turbochargers' => [['Turbocharger', 2_400_000, 6_800_000]],
            'oil-filters' => [['Oil Filter', 25_000, 90_000]],
            'air-filters' => [['Air Filter', 35_000, 140_000]],
            'fuel-filters' => [['Fuel Filter', 45_000, 190_000]],
            'cabin-filters' => [['Cabin Filter', 40_000, 160_000]],
            'shock-absorbers' => [['Shock Absorber Front', 240_000, 890_000], ['Shock Absorber Rear', 210_000, 760_000]],
            'springs' => [['Coil Spring Front', 180_000, 620_000]],
            'control-arms' => [['Control Arm Front Left', 200_000, 780_000], ['Control Arm Front Right', 200_000, 780_000]],
            'bushings' => [['Control Arm Bush', 45_000, 180_000]],
            'batteries' => [['Battery 60Ah 540A', 380_000, 780_000], ['Battery 74Ah 680A', 480_000, 980_000]],
            'alternators' => [['Alternator 140A', 1_100_000, 3_400_000]],
            'starters' => [['Starter Motor', 900_000, 2_800_000]],
            'sensors' => [['Crankshaft Sensor', 90_000, 420_000], ['ABS Sensor Front', 110_000, 460_000]],
            'headlights' => [['Headlight Left', 700_000, 2_600_000], ['Headlight Right', 700_000, 2_600_000]],
            'tail-lights' => [['Tail Light Left', 400_000, 1_400_000]],
            'bulbs' => [['Bulb H7 12V 55W', 15_000, 70_000]],
            'fog-lights' => [['Fog Light Front', 220_000, 780_000]],
            'floor-mats' => [['Rubber Floor Mat Set', 90_000, 320_000]],
            'seat-covers' => [['Seat Cover Set', 150_000, 520_000]],
            'steering-wheels' => [['Steering Wheel Cover', 60_000, 220_000]],
            'mirrors' => [['Wing Mirror Left', 280_000, 980_000], ['Wing Mirror Right', 280_000, 980_000]],
        ];
    }
}
