<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Models\Cms\OpenGraph;
use App\Domain\Repositories\Interfaces\ProductRepository;
use App\Domain\DataTables\ProductDataTable;
use App\Domain\Models\Cms\Page;
use App\Domain\Models\Locale\Country;
use App\Domain\Models\Locale\Locale;
use App\Domain\Models\Product\FormRevision;
use App\Domain\Models\Product\Hierarchy;
use App\Domain\Models\Product\Product;
use App\Domain\Models\Product\Question;
use App\Domain\Repositories\EloquentProductRepository;
use App\Events\EntityWasDeleted;
use App\Events\EntityWasUpdated;
use App\Http\Requests\Admin\ProductRequest;
use App\Modules\Media\EntityImageManager;
use Illuminate\Http\Request;

class ProductController extends BaseController
{

    /**
     * @var \App\Domain\Repositories\EloquentProductRepository
     */
    private $productRepository;

    public function __construct()
    {
        $this->productRepository = app()->make(EloquentProductRepository::class);
    }

    /**
     * @param Country $country
     * @param Locale $locale
     * @param Hierarchy $hierarchy
     * @param EntityImageManager $manager
     *
     * @return $this
     */
    public function getOverview(Country $country, Locale $locale, Hierarchy $hierarchy, EntityImageManager $manager)
    {
        return view('admin.products.overview', [
            'locale_codes' => $locale->getLocaleCodes(),
            'countries'    => $country->getActiveCountries(),
            'categories'   => $hierarchy->getList($this->getLocaleCode()),
        ]);
    }

    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function getOverviewData(Request $request, ProductDataTable $table)
    {
        return $table->make($request);
    }

    /**
     * @param Locale $locale
     *
     * @return $this
     */
    public function create(Locale $locale)
    {
        return view('admin.products.form')->with([
            'record'          => null,
            'action'          => 'create',
            'locale_codes'    => $locale->getLocaleCodes(),
            'list_products'   => Product::getListForLinks(),
            'parent_products' => Product::getParentsProductList($this->getLocaleCode()),
        ]);
    }

    /**
     * @param ProductRequest $request
     * @param EntityImageManager $uploader
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(ProductRequest $request, EntityImageManager $uploader)
    {
        $record = Product::create($request->all() + ['price' => 0]); // @todo: fix pricing..

        $formRevision = app()->make(FormRevision::class);
        $formRevision->createRevision(FormRevision::STATUS_PUBLISHED, $record->id);

        $record->hierarchies()->sync((array)$request->get('hierarchy_id'));
        $record->cmsPages()->sync((array)$request->get('page_id'));
        $record->crossSell()->sync((array)$request->get('cross_sell_product_id'));
        $record->belongsProducts()->sync((array)$request->get('product_belongs'));

        $record->openGraph()->associate(OpenGraph::create([
            'title'       => $request->get('open_graph_title'),
            'description' => $request->get('open_graph_description'),
        ]));
        $record->save();

        $this->handleFileUploads($request, $record, $uploader);

        $this->handleHighlightWords($request, $record);

        event(new EntityWasUpdated($record));

        flash()->success(trans('admin/product.saved'));

        return redirect()->route('admin.products.edit', $record->id);
    }

    /**
     * @param $id
     * @param Product $product
     * @param Locale $locale
     * @param Question $QuestionModel
     *
     * @return $this
     */
    public function edit($id, Product $product, Locale $locale, Question $QuestionModel, ProductRepository $productRepository)
    {
        /** @var Product $Product */
        $Product = $product
            ->with([
                'questions',
                'questions.answers',
                'linkedProducts',
                'belongsProducts',
                'openGraph',
            ])
            ->findOrFail($id);

        $highlightWords = '';

        foreach ($Product->highlightWords as $highlightWord) {
            $highlightWords .= $highlightWord->name . "\n";
        }

        $Product = $Product->toArray();

        return view('admin.products.form')->with([
            'record'          => $Product,
            'locale_codes'    => $locale->getLocaleCodes(),
            'action'          => 'edit',
            'list_products'   => Product::getListForLinks($id),
            'parent_products' => Product::getParentsProductList($this->getLocaleCode(), $id),
            'highlight_words' => $highlightWords,
        ]);
    }

    /**
     * @param ProductRequest $request
     * @param $id
     * @param Product $product
     * @param EntityImageManager $uploader
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(ProductRequest $request, $id, Product $product, EntityImageManager $uploader)
    {
        $data = $request->all();
        if ($request->has('season_featured') === false) {
            $data['season_featured'] = false;
        }
        /**
         * @var $record \App\Domain\Models\Product\Product
         */

        $record = $product->with(['openGraph'])->findOrFail($id);
        $record->update($data);

        $featured_pages = $request->get('feature_page');
        $pages = array_map(function($val) use ($featured_pages) {

            $return = [];
            $return['source_id'] = $val;

            if(is_null($featured_pages)) {
                return $return;
            }
            if(in_array($val, $featured_pages)) {
                $return['featured'] = true;
            }

            return $return;
        }, $request->get('page_id', []));

        $record->hierarchies()->sync((array)$request->get('hierarchy_id'));
        $record->cmsPages()->sync($pages);
        $record->crossSell()->sync((array)$request->get('cross_sell_product_id'));

        $belongs = (array) $request->get('product_belongs');
        $product_belongs = [];

        array_walk($belongs, function ($value, $index) use (&$product_belongs) {
            if (strlen($value) > 0 && is_numeric($value)) {
                $product_belongs[] = $value;
            }
        });

        $record->belongsProducts()->sync($product_belongs);
        $linked_products = array_pluck($record->linkedProducts, 'id');

        $attach_products = array_diff((array)$request->get('product_rel_id'), $linked_products);
        $detach_products = array_diff($linked_products, (array)$request->get('product_rel_id'));

        $record->linkedProducts()->sync((array)$request->get('product_rel_id'));

        if ($attach_products != []) {
            foreach ($attach_products as $row) {
                $data = $product->findOrFail($row);
                $data->linkedProducts()->attach($id);
            }
        }
        if ($detach_products != []) {
            foreach ($detach_products as $row) {
                $data = $product->findOrFail($row);
                $data->linkedProducts()->detach($id);
            }
        }
        if ($attach_products != [] || $detach_products != []) {
            $record->logProduct($id);
        }

        $open_graph_data = [
            'title'       => $request->get('open_graph_title'),
            'description' => $request->get('open_graph_description'),
        ];
        if($record->openGraph == null) {
            $record->openGraph()->associate(OpenGraph::create($open_graph_data));
            $record->save();
        }
        $record->openGraph()->update($open_graph_data);

        $this->handleFileUploads($request, $record, $uploader);

        $this->handleHighlightWords($request, $record);

        event(new EntityWasUpdated($record));

        flash()->success(trans('admin/product.saved'));

        return redirect()->route('admin.products.edit', $record->id);
    }

    /**
     * @param $id
     * @param Product $product
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove($id, Product $product)
    {
        /**
         * @var $record Product
         */
        $record = $product->findOrFail($id);
        $record->delete();
        flash()->success(trans('admin/product.deleted'));

        event(new EntityWasDeleted($record));

        return redirect()->route('admin.products.overview');
    }

    /**
     * @param Request $request
     * @param \App\Domain\Models\Product\Product $record
     * @param EntityImageManager $manager
     */
    protected function handleFileUploads(Request $request, $record, EntityImageManager $manager)
    {
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $record->image_name = $manager->upload(EntityImageManager::TYPE_PRODUCT, $record->slug, $file, $record->locale_code);
            $record->save();
        }
        if ($request->hasFile('open_graph_image')) {
            $file = $request->file('open_graph_image');
            $image_name = $manager->upload(EntityImageManager::TYPE_SOCIAL_OGP, 'product-' . $record->slug, $file, $record->locale_code);

            $record->openGraph()->update([
                'image'        => $image_name,
                'image_width'  => OpenGraph::IMAGE_WIDTH,
                'image_height' => OpenGraph::IMAGE_HEIGHT,
            ]);
        }
    }

    /**
     * @param ProductRequest $request
     * @param Product        $product
     *
     * @return $this
     */
    protected function handleHighlightWords(ProductRequest $request, Product $product)
    {
        if ($request->has('highlight_words')) {
            $requestedWords = explode("\n", str_replace(["\r\n", "\n\r", "\r"], "\n", trim($request->input('highlight_words'))));
            $existingWords = $product->highlightWords->pluck('name')->toArray();

            foreach (array_diff($requestedWords, $existingWords) as $highlightWord) {
                $product->highlightWords()->create([
                    'name' => $highlightWord,
                ]);
            }

            foreach (array_diff($existingWords, $requestedWords) as $highlightWord) {
                /** @var \App\Domain\Models\Product\HighlightWord $highlightWord */
                $highlightWord = $product->highlightWords->where('name', $highlightWord)->first();

                $highlightWord->delete();
            }
        }

        return $this;
    }

    /**
     * @param $id
     * @param Product $product
     * @param EntityImageManager $manager
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeImage($id, Product $product, EntityImageManager $manager)
    {
        $record = $product->findOrFail($id);
        $manager->removeImage(EntityImageManager::TYPE_PRODUCT, $record->image_name, $record->locale_code);
        $record->image_name = null;
        $record->save();
        flash()->success(trans('admin/image.deleted'));

        return redirect()->route('admin.products.edit', $record->id);
    }

    /**
     * @param $id
     * @param Product $product
     * @param EntityImageManager $manager
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeOpenGraphImage($id, Product $product, EntityImageManager $manager)
    {
        $record = $product->with(['openGraph'])->findOrFail($id);
        $manager->removeImage(EntityImageManager::TYPE_SOCIAL_OGP, $record->openGraph->image, $record->locale_code);
        $record->openGraph->image = null;
        $record->openGraph->image_height = null;
        $record->openGraph->image_width = null;
        $record->openGraph->save();
        flash()->success(trans('admin/image.deleted'));

        return redirect()->route('admin.products.edit', $record->id);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return string
     */
    public function ajax_getProducts(Request $request)
    {
        $locale_code = $request->get('locale_code', 'nl-NL');

        return $this->jsonResponse($this->productRepository->getByLocale($locale_code));
    }

    /**
     * @param Request $request
     * @param Page $PageModel
     * @param Product $ProductModel
     *
     * @return $this
     */
    public function ajax_showPagesForm(Request $request, Page $PageModel, Product $ProductModel)
    {
        $locale_code = $request->get('locale_code', $this->getLocaleCode());
        $product_id = $request->get('product_id');

        $pages = $PageModel->productPages()->localized($locale_code)->pluck('title', 'id')->all();

        $selectedIds = [];
        $featuredIds = [];
        if ($product_id) {
            $product = $ProductModel->findOrFail($product_id);
            $selected = $product->cmsPages()->get();

            foreach($selected as $page) {
                $selectedIds[] = $page->id;

                if($page->pivot->featured) {
                    $featuredIds[] = $page->id;
                }
            }
        }

        return view('admin.products.forms.pages')->with([
            'selected' => $selectedIds,
            'pages'    => $pages,
            'featured' => $featuredIds,
        ]);
    }

    /**
     * @param Request $request
     * @param Hierarchy $HierarchyModel
     * @param Product $ProductModel
     *
     * @return $this
     */
    public function ajax_showCategoriesForm(Request $request, Hierarchy $HierarchyModel, Product $ProductModel)
    {
        $checked = [];
        $locale_code = $request->get('locale_code', $this->getLocaleCode());
        $product_id = $request->get('product_id');

        $hierarchies = $HierarchyModel->getList($locale_code);

        if ($product_id) {
            $product = $ProductModel->findOrFail($product_id);
            $checked = $product->hierarchies()->pluck('hierarchy_id')->all();
        }

        return view('admin.products.forms.categories')->with([
            'checked'     => $checked,
            'hierarchies' => $hierarchies,
        ]);
    }

    /**
     * @param Request $request
     * @param Product $ProductModel
     *
     * @return $this
     */
    public function ajax_showCrossSellForm(Request $request, Product $ProductModel)
    {
        $checked = [];
        $locale_code = $request->get('locale_code', $this->getLocaleCode());
        $product_id = $request->get('product_id');

        $products = $ProductModel->getList($locale_code, $product_id);

        if ($product_id) {
            $product = $ProductModel->findOrFail($product_id);
            $checked = $product->crossSell()->pluck('cross_sell_product_id')->all();
        }

        return view('admin.products.forms.cross_sell')->with([
            'checked'  => $checked,
            'products' => $products,
        ]);
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public function ajax_getPrefillHeader(Request $request)
    {
        $locale_code = $request->get('locale_code', $this->getLocaleCode());
        $product_title = $request->get('product_title');

        return Product::getPrefilledHeaderTitle($product_title, $locale_code);
    }
}
