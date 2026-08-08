<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Category;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\FitmentSeederSmall;
use Database\Seeders\ProductSeederSmall;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The filter sidebar's option names: clickable, and announced by their own name.
 *
 * THE BUG THIS PINS. Every option row put the option's name in a SIBLING <div> of its
 * checkbox and gave the checkbox no id at all — so nothing associated the words "Brembo"
 * with the control they describe. Two consequences, one visible and one not:
 *
 *   - a screen reader read out "checkbox, not checked" thirty times in a row, because an
 *     input with no label, no aria-label and no title has no accessible name;
 *   - the only thing guaranteed to toggle the option was the control itself, so the target
 *     was as small as the theme drew it rather than as wide as the word.
 *
 * The fix is a <label for> inside the existing .brator-name span. That span keeps carrying
 * the theme's styling (and the colour swatch's inline border), and the label is a plain
 * inline element inheriting it — no new CSS class, which ThemeFidelityTest forbids.
 *
 * WHAT THESE TESTS CANNOT DO, said plainly. A PHPUnit test renders markup; it does not
 * click. None of this proves a click on the word reaches the checkbox — that needs a real
 * browser, and it is what the operator verifies by hand. What it does prove is the part
 * that fails silently and invisibly: that the association exists, that it points at the
 * right control, and that no two controls claim the same id.
 */
final class FilterLabelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class, FitmentSeederSmall::class]);
    }

    public function test_every_filter_option_has_a_label_a_screen_reader_can_read(): void
    {
        [$xpath, $sidebar] = $this->sidebar();

        $controls = $this->controls($xpath, $sidebar);

        // Guard the guard: with an empty sidebar every assertion below would pass while
        // testing nothing. All three groups plus the brands and the ratings render here.
        $this->assertGreaterThan(10, $controls->count(),
            'The sidebar rendered almost no options, so this test is not checking anything.');

        foreach ($controls as $control) {
            $name = $control->getAttribute('name');
            $value = $control->getAttribute('value');
            $id = $control->getAttribute('id');

            $this->assertNotSame('', $id,
                "The filter option {$name}={$value} has no id, so no label can point at it.");

            $labels = $xpath->query('//label[@for="'.$id.'"]');

            $this->assertNotFalse($labels);
            $this->assertSame(1, $labels->count(),
                "The filter option {$name}={$value} (id {$id}) is named by ".$labels->count()
                .' labels. Exactly one is what gives it an accessible name.');
        }
    }

    public function test_a_label_only_ever_points_at_the_control_in_its_own_row(): void
    {
        /*
         | The failure this pins is the nasty one: a label that names the wrong option. It
         | reads perfectly on screen — the words are in the right place — and ticks the box
         | one row up. Asserting the label lives in the SAME row as the control it targets is
         | what makes that impossible, whatever the id scheme does later.
        */
        [$xpath, $sidebar] = $this->sidebar();

        foreach ($this->controls($xpath, $sidebar) as $control) {
            $row = $control->parentNode;
            $this->assertInstanceOf(DOMElement::class, $row);

            $labels = $row->getElementsByTagName('label');

            $this->assertSame(1, $labels->count(),
                'The row for '.$control->getAttribute('name').'='.$control->getAttribute('value')
                .' holds '.$labels->count().' labels rather than one.');

            $label = $labels->item(0);
            $this->assertInstanceOf(DOMElement::class, $label);

            $this->assertSame($control->getAttribute('id'), $label->getAttribute('for'),
                'A label names a control that is not the one in its own row, so clicking these '
                .'words ticks a different filter.');

            // And it says the option's own name — not the count beside it. A label wrapping
            // "(12)" has a screen reader read the facet number as part of the option.
            $this->assertSame($this->expectedName($control), $this->text($label),
                'The label does not read as the name of the option it ticks.');

            $this->assertStringNotContainsString('(', $this->text($label),
                'The facet count has been pulled inside the label, so the option is announced '
                .'as "Brembo (147)".');
        }
    }

    public function test_no_two_things_on_the_listing_page_share_an_id(): void
    {
        /*
         | THE ASSERTION MOST WORTH HAVING, because a duplicate id fails in total silence:
         | the browser associates the label with whichever matching control it finds FIRST,
         | so one row's words tick another row's box and nothing anywhere reports it.
         |
         | Page-wide rather than sidebar-wide on purpose — the price slider mount and the
         | clear-filters form carry ids of their own, and an option colliding with one of
         | those is the same bug.
        */
        $xpath = $this->document($this->listingHtml())[0];

        $ids = [];

        $nodes = $xpath->query('//*[@id]');
        $this->assertNotFalse($nodes);

        foreach ($nodes as $node) {
            $this->assertInstanceOf(DOMElement::class, $node);
            $ids[] = $node->getAttribute('id');
        }

        $duplicates = array_values(array_unique(array_diff_assoc($ids, array_unique($ids))));

        $this->assertSame([], $duplicates,
            "These ids appear more than once on the listing page, so a <label for> pointing at \n"
            ."one of them activates whichever control the browser happens to find first:\n  "
            .implode("\n  ", $duplicates));
    }

    public function test_an_options_id_comes_from_its_own_value_and_not_its_position(): void
    {
        /*
         | Not a style rule — a correctness one, and the reason is in storefront.js.
         |
         | syncFilterOptions() patches this sidebar row by row after an in-place filter
         | change. It pairs old rows with new ones on the input's name + value, MOVES the
         | survivors with appendChild and clones the arrivals, so a row's index in the list
         | changes while the node itself is reused. An id encoding a position would travel
         | with a moved row and start naming a different option — and, per the test above,
         | ticking it. Keyed on the value, the id follows the row wherever it lands.
        */
        [$xpath, $sidebar] = $this->sidebar();

        foreach ($this->controls($xpath, $sidebar) as $control) {
            $this->assertSame($this->expectedId($control), $control->getAttribute('id'),
                'The id of '.$control->getAttribute('name').'='.$control->getAttribute('value')
                .' is not derived from its own value.');
        }
    }

    public function test_an_option_keeps_its_id_when_the_list_around_it_shortens(): void
    {
        /*
         | The same rule, observed rather than read off the scheme: brands with no matches are
         | not rendered at all, so a rating filter genuinely shortens the brand list and every
         | brand below a removed one shifts up. Any brand on both pages must come back with
         | the id it had.
         |
         | The last brand is TICKED as well, and that is what keeps this test from depending on
         | how much the fixture happens to contain: a brand the shopper has ticked is always
         | rendered even at a count of zero, so there is guaranteed to be something to compare,
         | and being last alphabetically it is the row most likely to have moved.
        */
        $before = $this->idsByValue('brand[]', $this->listingHtml());

        $this->assertNotSame([], $before, 'No brand options rendered, so nothing was compared.');

        $ticked = (string) array_key_last($before);

        $after = $this->idsByValue('brand[]', $this->listingHtml([
            'rating' => 4,
            'brand' => [$ticked],
        ]));

        $this->assertArrayHasKey($ticked, $after,
            "The ticked brand '{$ticked}' vanished from the sidebar, which would leave a shopper "
            .'no way to untick it.');

        $shared = array_intersect_key($before, $after);

        foreach ($shared as $value => $id) {
            $this->assertSame($id, $after[$value],
                "The brand '{$value}' came back with a different id after the list shortened, "
                .'which means the id depends on where the row sits.');
        }
    }

    public function test_the_brand_search_box_can_still_read_every_row(): void
    {
        /*
         | The sidebar's brand search box filters client-side: storefront.js reads
         | data-filter-label off each row and hides the ones that do not match with
         | style.display. Restructuring what is INSIDE the row must not disturb that
         | attribute, or typing "brem" hides every brand including Brembo.
        */
        [$xpath, $sidebar] = $this->sidebar();

        $brands = $xpath->query('.//input[@name="brand[]"]', $sidebar);
        $this->assertNotFalse($brands);
        $this->assertGreaterThan(0, $brands->count(), 'No brand options rendered.');

        foreach ($brands as $brand) {
            $row = $brand->parentNode;
            $this->assertInstanceOf(DOMElement::class, $row);

            $this->assertTrue($row->hasAttribute('data-filter-label'),
                'A brand row lost data-filter-label, so the search box can no longer match it.');

            // The searchable text and the readable text are the same words, which is what
            // stops the box hiding a row whose visible name does match.
            $labels = $row->getElementsByTagName('label');
            $label = $labels->item(0);
            $this->assertInstanceOf(DOMElement::class, $label);

            $this->assertSame($row->getAttribute('data-filter-label'), $this->text($label),
                'What the search box matches on and what the shopper reads have drifted apart.');
        }
    }

    public function test_the_labels_bring_no_new_css_class_with_them(): void
    {
        /*
         | The binding constraint of this whole project: the purchased theme's styling is not
         | ours to change, and ThemeFidelityTest fails on any class the theme does not ship.
         | The labels are therefore bare — they inherit .brator-name, which already styles
         | these words. Asserted here as well because that test only walks the homepage, and
         | the sidebar is not on it.
        */
        [$xpath, $sidebar] = $this->sidebar();

        $labels = $xpath->query('.//label', $sidebar);
        $this->assertNotFalse($labels);
        $this->assertGreaterThan(10, $labels->count(), 'The option labels are not rendering.');

        foreach ($labels as $label) {
            $this->assertInstanceOf(DOMElement::class, $label);

            $this->assertFalse($label->hasAttribute('class'),
                'A filter label carries a class of its own. The theme styles these words through '
                .'.brator-name; a new class here is new styling.');
        }
    }

    /**
     * The option name the label is expected to read out.
     *
     * Derived from the control itself rather than a fixed list, so this keeps holding when
     * the seeded brands and attribute options change.
     */
    private function expectedName(DOMElement $control): string
    {
        $value = $control->getAttribute('value');

        if ($control->getAttribute('name') === 'rating') {
            return $value === '' ? 'Any rating' : $value.' stars & up';
        }

        $row = $control->parentNode;

        // A brand row already declares its own display name for the search box; anything
        // else shows the stored option value verbatim.
        if ($row instanceof DOMElement && $row->hasAttribute('data-filter-label')) {
            return $row->getAttribute('data-filter-label');
        }

        return $value;
    }

    /** The id the option must have, rebuilt here from its name and value alone. */
    private function expectedId(DOMElement $control): string
    {
        $name = $control->getAttribute('name');
        $value = $control->getAttribute('value');

        if ($name === 'brand[]') {
            return 'filter-brand-'.$value;
        }

        if ($name === 'rating') {
            return 'filter-rating-'.($value === '' ? 'any' : $value);
        }

        if (preg_match('/^attr\[([^]]+)]\[]$/', $name, $matches) === 1) {
            return 'filter-attr-'.$matches[1].'-'.Str::slug($value);
        }

        $this->fail("Unrecognised filter control '{$name}'. If a new filter kind was added to the "
            .'sidebar, give it an id derived from its value and teach this test the shape.');
    }

    /**
     * value => id for one filter group on a rendered page.
     *
     * @return array<string, string>
     */
    private function idsByValue(string $name, string $html): array
    {
        [$xpath, $sidebar] = $this->document($html);

        $nodes = $xpath->query('.//input[@name="'.$name.'"]', $sidebar);
        $this->assertNotFalse($nodes);

        $ids = [];

        foreach ($nodes as $node) {
            $this->assertInstanceOf(DOMElement::class, $node);
            $ids[$node->getAttribute('value')] = $node->getAttribute('id');
        }

        return $ids;
    }

    /**
     * Every option control in the sidebar.
     *
     * Checkboxes and radios only. The price block holds two number inputs driving the
     * theme's noUiSlider and the brand block a search box; none of them is an option, and
     * none is in scope here.
     */
    private function controls(DOMXPath $xpath, DOMElement $sidebar): DOMNodeList
    {
        $controls = $xpath->query('.//input[@type="checkbox" or @type="radio"]', $sidebar);

        $this->assertNotFalse($controls);

        return $controls;
    }

    /** @return array{0: DOMXPath, 1: DOMElement} */
    private function sidebar(): array
    {
        return $this->document($this->listingHtml());
    }

    /**
     * The rendered markup, parsed, plus the filter form inside it.
     *
     * Parsed rather than string-matched on purpose: the options are seeded data and change,
     * so a test pinned to "Brembo" would only ever prove that Brembo is still seeded.
     *
     * @return array{0: DOMXPath, 1: DOMElement}
     */
    private function document(string $html): array
    {
        $doc = new DOMDocument;

        // The theme's markup is not XML and libxml complains about it at length; the page
        // declares <meta charset="UTF-8"> so the encoding is read from the document.
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);

        $sidebar = $xpath->query('//form[@data-listing-filters]')->item(0);

        $this->assertInstanceOf(DOMElement::class, $sidebar,
            'No filter form on the listing page, so there is nothing to check.');

        return [$xpath, $sidebar];
    }

    /**
     * A real listing page: a leaf category, which is where the attribute groups hang.
     *
     * category_attributes is scoped to leaf categories, so a department renders brands and
     * ratings but no Origins or Materials — and the attribute ids are the ones with the
     * interesting shape.
     *
     * @param  array<string, mixed>  $query
     */
    private function listingHtml(array $query = []): string
    {
        $category = Category::query()->where('depth', 1)->whereHas('products')->firstOrFail();

        $url = '/shop/'.$category->slug.($query === [] ? '' : '?'.http_build_query($query));

        return $this->get($url)->assertOk()->getContent();
    }

    /** An element's text with the whitespace and entities a template leaves behind resolved. */
    private function text(DOMElement $element): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $element->textContent));
    }
}
