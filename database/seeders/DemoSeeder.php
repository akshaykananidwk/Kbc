<?php
declare(strict_types=1);

namespace Database\Seeders;

/**
 * Optional demo content. The admin can remove all of it in one click from
 * Settings -> Demo Data.
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedGifts();
        $this->seedParticipants();
        $this->seedQuestions();
        $this->attachGiftsToLevels();
    }

    private function seedGifts(): void
    {
        $gifts = [
            ['Prasad Hamper', 'A festive hamper with sweets and dry fruits.', 1100, 25],
            ['Smart Watch', 'Fitness smart watch with heart rate monitor.', 2500, 10],
            ['Wireless Headphones', 'Over-ear bluetooth headphones.', 3500, 10],
            ['Silver Ganpati Coin', '10 gram pure silver Ganpati coin.', 8000, 5],
            ['Grand Prize Trophy', 'Ganpati Bapa Quiz Show champion trophy with gift voucher.', 25000, 2],
        ];

        foreach ($gifts as $index => [$name, $description, $value, $quantity]) {
            $this->firstOrCreate('gifts', ['name' => $name], [
                'description'    => $description,
                'value_amount'   => $value,
                'quantity_total' => $quantity,
                'quantity_used'  => 0,
                'status'         => 'active',
                'sort_order'     => ($index + 1) * 10,
                'created_at'     => $this->now,
                'updated_at'     => $this->now,
            ]);
        }
    }

    private function seedParticipants(): void
    {
        $participants = [
            ['GQ-001', 'Rajesh Patel', '9876543210', 'Rajkot', 34],
            ['GQ-002', 'Meera Shah', '9876543211', 'Ahmedabad', 27],
            ['GQ-003', 'Kiran Joshi', '9876543212', 'Dwarka', 41],
        ];

        foreach ($participants as [$reg, $name, $mobile, $city, $age]) {
            $this->firstOrCreate('participants', ['registration_no' => $reg], [
                'name'       => $name,
                'mobile'     => $mobile,
                'city'       => $city,
                'age'        => $age,
                'status'     => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedQuestions(): void
    {
        $categories = [];
        foreach ($this->db->select('SELECT id, slug FROM question_categories') as $row) {
            $categories[(string) $row['slug']] = (int) $row['id'];
        }

        $questions = [
            [
                'ganpati-bapa', 'easy', 1, 30,
                'ગણેશ ચતુર્થી કયા મહિનામાં ઉજવવામાં આવે છે?',
                ['શ્રાવણ', 'ભાદરવો', 'આસો', 'કારતક'], 'B',
                'ગણેશ ચતુર્થી ભાદરવા સુદ ચોથના દિવસે ઉજવવામાં આવે છે.',
            ],
            [
                'ganpati-bapa', 'easy', 2, 30,
                'ભગવાન ગણેશનું વાહન કયું છે?',
                ['મોર', 'ઉંદર', 'સિંહ', 'બળદ'], 'B',
                'મૂષક (ઉંદર) ભગવાન ગણેશનું વાહન છે.',
            ],
            [
                'general-knowledge', 'easy', 3, 30,
                'How many colours are there in a rainbow?',
                ['5', '6', '7', '8'], 'C',
                'A rainbow has seven colours - VIBGYOR.',
            ],
            [
                'gujarat', 'medium', 4, 45,
                'ગુજરાતની રાજધાની કઈ છે?',
                ['અમદાવાદ', 'ગાંધીનગર', 'સુરત', 'વડોદરા'], 'B',
                'ગાંધીનગર ગુજરાતની રાજધાની છે.',
            ],
            [
                'india', 'medium', 5, 45,
                'भारत का राष्ट्रीय पक्षी कौन सा है?',
                ['मोर', 'तोता', 'कबूतर', 'बाज'], 'A',
                'मोर भारत का राष्ट्रीय पक्षी है।',
            ],
            [
                'dwarka', 'medium', 6, 45,
                'દ્વારકાધીશ મંદિર કયા રાજ્યમાં આવેલું છે?',
                ['રાજસ્થાન', 'મહારાષ્ટ્ર', 'ગુજરાત', 'મધ્ય પ્રદેશ'], 'C',
                'દ્વારકાધીશ મંદિર ગુજરાતના દેવભૂમિ દ્વારકા જિલ્લામાં આવેલું છે.',
            ],
            [
                'science', 'hard', 7, 60,
                'Which gas do plants absorb from the atmosphere during photosynthesis?',
                ['Oxygen', 'Nitrogen', 'Carbon dioxide', 'Hydrogen'], 'C',
                'Plants absorb carbon dioxide and release oxygen during photosynthesis.',
            ],
            [
                'history', 'hard', 8, 60,
                'મહાત્મા ગાંધીએ દાંડી કૂચ કયા વર્ષે શરૂ કરી હતી?',
                ['1920', '1930', '1942', '1947'], 'B',
                '12 માર્ચ 1930ના રોજ દાંડી કૂચ શરૂ થઈ હતી.',
            ],
            [
                'religion-culture', 'hard', 9, 90,
                'અષ્ટવિનાયકના કેટલા મંદિરો છે?',
                ['5', '7', '8', '12'], 'C',
                'અષ્ટવિનાયક એટલે મહારાષ્ટ્રમાં આવેલા ગણપતિના આઠ પ્રાચીન મંદિરો.',
            ],
            [
                'general-knowledge', 'expert', 10, 90,
                'Which Indian state has the longest coastline?',
                ['Tamil Nadu', 'Gujarat', 'Andhra Pradesh', 'Maharashtra'], 'B',
                'Gujarat has the longest coastline in India at about 1,600 km.',
            ],
            [
                'sports', 'medium', null, 45,
                'ક્રિકેટમાં એક ઓવરમાં કેટલા બોલ હોય છે?',
                ['4', '5', '6', '8'], 'C',
                'આધુનિક ક્રિકેટમાં એક ઓવરમાં છ બોલ હોય છે.',
            ],
            [
                'technology', 'medium', null, 45,
                'What does "HTTP" stand for?',
                ['Hyper Transfer Text Protocol', 'HyperText Transfer Protocol', 'High Transfer Text Process', 'HyperText Transmission Port'], 'B',
                'HTTP is the HyperText Transfer Protocol used by the web.',
            ],
            [
                'current-affairs', 'medium', null, 45,
                'ભારતનું ચંદ્રયાન-3 મિશન ચંદ્રના કયા ભાગ પર ઉતર્યું હતું?',
                ['ઉત્તર ધ્રુવ', 'દક્ષિણ ધ્રુવ', 'વિષુવવૃત્ત', 'પૂર્વ તરફ'], 'B',
                'ચંદ્રયાન-3 ચંદ્રના દક્ષિણ ધ્રુવ પ્રદેશમાં ઉતર્યું હતું.',
            ],
            [
                'ganpati-bapa', 'easy', null, 30,
                'ગણપતિ બાપાને કયું ભોજન સૌથી પ્રિય માનવામાં આવે છે?',
                ['લાડુ / મોદક', 'ખીર', 'પુરી', 'હલવો'], 'A',
                'મોદક ગણપતિ બાપાનું પ્રિય નૈવેદ્ય માનવામાં આવે છે.',
            ],
        ];

        $sort = 0;
        foreach ($questions as [$categorySlug, $difficulty, $level, $time, $text, $options, $correct, $explanation]) {
            $sort += 10;
            $existing = $this->db->selectOne('SELECT id FROM questions WHERE question_text = ? LIMIT 1', [$text]);
            if ($existing !== null) {
                continue;
            }

            $questionId = $this->db->insert('questions', [
                'category_id'       => $categories[$categorySlug] ?? null,
                'question_text'     => $text,
                'correct_option'    => $correct,
                'explanation'       => $explanation,
                'difficulty'        => $difficulty,
                'time_limit'        => $time,
                'prize_level'       => $level,
                'lifelines_allowed' => 1,
                'status'            => 'active',
                'sort_order'        => $sort,
                'created_at'        => $this->now,
                'updated_at'        => $this->now,
            ]);

            foreach (['A', 'B', 'C', 'D'] as $index => $key) {
                $this->db->insert('question_options', [
                    'question_id' => $questionId,
                    'option_key'  => $key,
                    'option_text' => $options[$index],
                    'sort_order'  => $index,
                ]);
            }
        }
    }

    private function attachGiftsToLevels(): void
    {
        $map = [
            3  => 'Prasad Hamper',
            5  => 'Smart Watch',
            7  => 'Wireless Headphones',
            9  => 'Silver Ganpati Coin',
            10 => 'Grand Prize Trophy',
        ];

        foreach ($map as $level => $giftName) {
            $gift = $this->db->selectOne('SELECT id FROM gifts WHERE name = ? LIMIT 1', [$giftName]);
            if ($gift === null) {
                continue;
            }
            $this->db->run(
                'UPDATE prize_levels SET gift_id = ?, updated_at = ? WHERE level_no = ? AND gift_id IS NULL',
                [(int) $gift['id'], $this->now, $level]
            );
        }
    }
}
