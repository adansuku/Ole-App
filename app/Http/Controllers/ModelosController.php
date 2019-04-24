<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use TCG\Voyager\Database\Schema\SchemaManager;
use TCG\Voyager\Events\BreadDataAdded;
use TCG\Voyager\Events\BreadDataDeleted;
use TCG\Voyager\Events\BreadDataUpdated;
use TCG\Voyager\Events\BreadImagesDeleted;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\Traits\BreadRelationshipParser;
use TCG\Voyager\Http\Controllers\Controller;
use \PDF;

class ModelosController extends Controller
{
    use BreadRelationshipParser;
    //***************************************
    //               ____
    //              |  _ \
    //              | |_) |
    //              |  _ <
    //              | |_) |
    //              |____/
    //
    //      Browse our Data Type (B)READ
    //
    //****************************************

    public function index(Request $request)
    {
        // GET THE SLUG, ex. 'posts', 'pages', etc.
        $slug = 'modelos';

        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        // Check permission
        $this->authorize('browse', app($dataType->model_name));

        $getter = $dataType->server_side ? 'paginate' : 'get';

        $search = (object) ['value' => $request->get('s'), 'key' => $request->get('key'), 'filter' => $request->get('filter')];
        $searchable = $dataType->server_side ? array_keys(SchemaManager::describeTable(app($dataType->model_name)->getTable())->toArray()) : '';
        $orderBy = $request->get('order_by');
        $sortOrder = $request->get('sort_order', null);

        // Next Get or Paginate the actual content from the MODEL that corresponds to the slug DataType
        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);
            $query = $model::select('*');

            $relationships = $this->getRelationships($dataType);

            // If a column has a relationship associated with it, we do not want to show that field
            $this->removeRelationshipField($dataType, 'browse');

            if ($search->value && $search->key && $search->filter) {
                $search_filter = ($search->filter == 'equals') ? '=' : 'LIKE';
                $search_value = ($search->filter == 'equals') ? $search->value : '%'.$search->value.'%';
                $query->where($search->key, $search_filter, $search_value);
            }

            if ($orderBy && in_array($orderBy, $dataType->fields())) {
                $querySortOrder = (!empty($sortOrder)) ? $sortOrder : 'DESC';
                $dataTypeContent = call_user_func([
                    $query->with($relationships)->orderBy($orderBy, $querySortOrder),
                    $getter,
                ]);
            } elseif ($model->timestamps) {
                $dataTypeContent = call_user_func([$query->latest($model::CREATED_AT), $getter]);
            } else {
                $dataTypeContent = call_user_func([$query->with($relationships)->orderBy($model->getKeyName(), 'DESC'), $getter]);
            }

            // Replace relationships' keys for labels and create READ links if a slug is provided.
            $dataTypeContent = $this->resolveRelations($dataTypeContent, $dataType);
        } else {
            // If Model doesn't exist, get data from table name
            $dataTypeContent = call_user_func([DB::table($dataType->name), $getter]);
            $model = false;
        }

        // Check if BREAD is Translatable
        if (($isModelTranslatable = is_bread_translatable($model))) {
            $dataTypeContent->load('translations');
        }

        // Check if server side pagination is enabled
        $isServerSide = isset($dataType->server_side) && $dataType->server_side;

        $view = 'voyager::bread.browse-modelos';

        if (view()->exists("voyager::$slug.browse-modelos")) {
            $view = "voyager::$slug.browse-modelos";
        }

        $deportes = DB::table('data_rows')
                   ->where('field', 'deportes')
                   ->where('data_type_id', $dataType->id)
                   ->first();
        if ($deportes){
            $deportes = json_decode($deportes->details);
            $deportes = $deportes->options;
        }

        $tatuajes = DB::table('data_rows')
                   ->where('field', 'tatuajes')
                   ->where('data_type_id', $dataType->id)
                   ->first();
        if ($tatuajes){
            $tatuajes = json_decode($tatuajes->details);
            $tatuajes = $tatuajes->options;
        }
        
        $cicatrices = DB::table('data_rows')
                   ->where('field', 'cicatrices')
                   ->where('data_type_id', $dataType->id)
                   ->first();
        if ($cicatrices){
            $cicatrices = json_decode($cicatrices->details);
            $cicatrices = $cicatrices->options;
        }

        $piercings = DB::table('data_rows')
                   ->where('field', 'piercings')
                   ->where('data_type_id', $dataType->id)
                   ->first();
        if ($piercings){
            $piercings = json_decode($piercings->details);
            $piercings = $piercings->options;
        }

        $deportes = DB::table('data_rows')
                   ->where('field', 'deportes')
                   ->where('data_type_id', $dataType->id)
                   ->first();
        if ($deportes){
            $deportes = json_decode($deportes->details);
            $deportes = $deportes->options;
        }




        return Voyager::view($view, compact(
            'dataType',
            'dataTypeContent',
            'isModelTranslatable',
            'search',
            'orderBy',
            'sortOrder',
            'searchable',
            'isServerSide'
        ));
    }

    //***************************************
    //                _____
    //               |  __ \
    //               | |__) |
    //               |  _  /
    //               | | \ \
    //               |_|  \_\
    //
    //  Read an item of our Data Type B(R)EAD
    //
    //****************************************

    public function show(Request $request, $id)
    {
        $slug = $this->getSlug($request); 

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        // Compatibility with Model binding.
        $id = $id instanceof Model ? $id->{$id->getKeyName()} : $id;

        $relationships = $this->getRelationships($dataType);
        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);
            $dataTypeContent = call_user_func([$model->with($relationships), 'findOrFail'], $id);
        } else {
            // If Model doest exist, get data from table name
            $dataTypeContent = DB::table($dataType->name)->where('id', $id)->first();
        }

        // Replace relationships' keys for labels and create READ links if a slug is provided.
        $dataTypeContent = $this->resolveRelations($dataTypeContent, $dataType, true);

        // If a column has a relationship associated with it, we do not want to show that field
        $this->removeRelationshipField($dataType, 'read');

        // Check permission
        $this->authorize('read', $dataTypeContent);

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($dataTypeContent);

        $view = 'voyager::bread.read-modelos';

        if (view()->exists("voyager::$slug.read-modelos")) {
            $view = "voyager::$slug.read-modelos";
        }

        return Voyager::view($view, compact('dataType', 'dataTypeContent', 'isModelTranslatable'));
    }

    //***************************************
    //                ______
    //               |  ____|
    //               | |__
    //               |  __|
    //               | |____
    //               |______|
    //
    //  Edit an item of our Data Type BR(E)AD
    //
    //****************************************

    public function edit(Request $request, $id)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        // Compatibility with Model binding.
        $id = $id instanceof Model ? $id->{$id->getKeyName()} : $id;

        $relationships = $this->getRelationships($dataType);

        $dataTypeContent = (strlen($dataType->model_name) != 0)
            ? app($dataType->model_name)->with($relationships)->findOrFail($id)
            : DB::table($dataType->name)->where('id', $id)->first(); // If Model doest exist, get data from table name

        foreach ($dataType->editRows as $key => $row) {
            $details = json_decode($row->details);
            $dataType->editRows[$key]['col_width'] = isset($details->width) ? $details->width : 100;
        }

        // If a column has a relationship associated with it, we do not want to show that field
        $this->removeRelationshipField($dataType, 'edit');

        // Check permission
        $this->authorize('edit', $dataTypeContent);

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($dataTypeContent);

        $view = 'voyager::bread.edit-modelos';

        if (view()->exists("voyager::$slug.edit-modelos")) {
            $view = "voyager::$slug.edit-modelos";
        }

        return Voyager::view($view, compact('dataType', 'dataTypeContent', 'isModelTranslatable'));
    }

    // POST BR(E)AD
    public function update(Request $request, $id)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

	    if (!empty($request->tatuajes)){
		    $field = DB::table('data_rows')
		               ->where('field', 'tatuajes')
		               ->where('data_type_id', $dataType->id)
		               ->first();


		    $row_details = json_decode($field->details, true);
		    $tatoo_options = $row_details['options'];

		    $diff = array_diff($request->tatuajes, array_keys($tatoo_options));
		    $diff = array_combine($diff, $diff);


		    $row_details['options'] = $tatoo_options + $diff;

		    DB::table('data_rows')
		      ->where('field', 'tatuajes')
		      ->where('data_type_id', $dataType->id)
		      ->update(['details' => json_encode($row_details)]);
	    }

        if (!empty($request->cicatrices)){
            $field = DB::table('data_rows')
                       ->where('field', 'cicatrices')
                       ->where('data_type_id', $dataType->id)
                       ->first();


            $row_details = json_decode($field->details, true);
            $tatoo_options = $row_details['options'];

            $diff = array_diff($request->cicatrices, array_keys($tatoo_options));
            $diff = array_combine($diff, $diff);


            $row_details['options'] = $tatoo_options + $diff;

            DB::table('data_rows')
              ->where('field', 'cicatrices')
              ->where('data_type_id', $dataType->id)
              ->update(['details' => json_encode($row_details)]);
        }

        if (!empty($request->piercings)){
            $field = DB::table('data_rows')
                       ->where('field', 'piercings')
                       ->where('data_type_id', $dataType->id)
                       ->first();


            $row_details = json_decode($field->details, true);
            $tatoo_options = $row_details['options'];

            $diff = array_diff($request->piercings, array_keys($tatoo_options));
            $diff = array_combine($diff, $diff);


            $row_details['options'] = $tatoo_options + $diff;

            DB::table('data_rows')
              ->where('field', 'piercings')
              ->where('data_type_id', $dataType->id)
              ->update(['details' => json_encode($row_details)]);
        }

        if (!empty($request->deportes)){
            $field = DB::table('data_rows')
                       ->where('field', 'deportes')
                       ->where('data_type_id', $dataType->id)
                       ->first();


            $row_details = json_decode($field->details, true);
            $tatoo_options = $row_details['options'];

            $diff = array_diff($request->deportes, array_keys($tatoo_options));
            $diff = array_combine($diff, $diff);


            $row_details['options'] = $tatoo_options + $diff;

            DB::table('data_rows')
              ->where('field', 'deportes')
              ->where('data_type_id', $dataType->id)
              ->update(['details' => json_encode($row_details)]);
        }

        if (!empty($request->cualidades)){
            $field = DB::table('data_rows')
                       ->where('field', 'cualidades')
                       ->where('data_type_id', $dataType->id)
                       ->first();


            $row_details = json_decode($field->details, true);
            $tatoo_options = $row_details['options'];

            $diff = array_diff($request->cualidades, array_keys($tatoo_options));
            $diff = array_combine($diff, $diff);


            $row_details['options'] = $tatoo_options + $diff;

            DB::table('data_rows')
              ->where('field', 'cualidades')
              ->where('data_type_id', $dataType->id)
              ->update(['details' => json_encode($row_details)]);
        }

        // Compatibility with Model binding.
        //$id = $id instanceof Model ? $id->{$id->getKeyName()} : $id;

        $data = call_user_func([$dataType->model_name, 'findOrFail'], $id);

        // Check permission
        $this->authorize('edit', $data);

        // Validate fields with ajax
        $val = $this->validateBread($request->all(), $dataType->editRows, $slug, $id);

        if ($val->fails()) {
            return response()->json(['errors' => $val->messages()]);
        }

        if (!$request->ajax()) {
            $this->insertUpdateData($request, $slug, $dataType->editRows, $data);

            event(new BreadDataUpdated($dataType, $data));

            return redirect()
                ->route("voyager.{$dataType->slug}.index")
                ->with([
                    'message'    => __('voyager.generic.successfully_updated')." {$dataType->display_name_singular}",
                    'alert-type' => 'success',
                ]);
        }
    }

    //***************************************
    //
    //                   /\
    //                  /  \
    //                 / /\ \
    //                / ____ \
    //               /_/    \_\
    //
    //
    // Add a new item of our Data Type BRE(A)D
    //
    //****************************************

    public function create(Request $request)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        // Check permission
        $this->authorize('add', app($dataType->model_name));

        $dataTypeContent = (strlen($dataType->model_name) != 0)
                            ? new $dataType->model_name()
                            : false;

        foreach ($dataType->addRows as $key => $row) {
            $details = json_decode($row->details);
            $dataType->addRows[$key]['col_width'] = isset($details->width) ? $details->width : 100;
        }

        // If a column has a relationship associated with it, we do not want to show that field
        $this->removeRelationshipField($dataType, 'add');

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($dataTypeContent);

        $view = 'voyager::bread.edit-modelos';

        if (view()->exists("voyager::$slug.edit-modelos")) {
            $view = "voyager::$slug.edit-modelos";
        }
//        return compact('dataType', 'dataTypeContent', 'isModelTranslatable');

        return Voyager::view($view, compact('dataType', 'dataTypeContent', 'isModelTranslatable'));
    }

    /**
     * POST BRE(A)D - Store data.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();



	    if (!empty($request->tatuajes)){
		    $field = DB::table('data_rows')
		               ->where('field', 'tatuajes')
		               ->where('data_type_id', $dataType->id)
		               ->first();


		    $row_details = json_decode($field->details, true);
		    $tatoo_options = $row_details['options'];

		    $diff = array_diff($request->tatuajes, array_keys($tatoo_options));
		    $diff = array_combine($diff, $diff);


		    $row_details['options'] = $tatoo_options + $diff;

		    DB::table('data_rows')
		      ->where('field', 'tatuajes')
		      ->where('data_type_id', $dataType->id)
		      ->update(['details' => json_encode($row_details)]);
	    }

        if (!empty($request->cicatrices)){
            $field = DB::table('data_rows')
                       ->where('field', 'cicatrices')
                       ->where('data_type_id', $dataType->id)
                       ->first();


            $row_details = json_decode($field->details, true);
            $tatoo_options = $row_details['options'];

            $diff = array_diff($request->cicatrices, array_keys($tatoo_options));
            $diff = array_combine($diff, $diff);


            $row_details['options'] = $tatoo_options + $diff;

            DB::table('data_rows')
              ->where('field', 'cicatrices')
              ->where('data_type_id', $dataType->id)
              ->update(['details' => json_encode($row_details)]);
        }

        if (!empty($request->piercings)){
            $field = DB::table('data_rows')
                       ->where('field', 'piercings')
                       ->where('data_type_id', $dataType->id)
                       ->first();


            $row_details = json_decode($field->details, true);
            $tatoo_options = $row_details['options'];

            $diff = array_diff($request->piercings, array_keys($tatoo_options));
            $diff = array_combine($diff, $diff);


            $row_details['options'] = $tatoo_options + $diff;

            DB::table('data_rows')
              ->where('field', 'piercings')
              ->where('data_type_id', $dataType->id)
              ->update(['details' => json_encode($row_details)]);
        }

        if (!empty($request->deportes)){
            $field = DB::table('data_rows')
                       ->where('field', 'deportes')
                       ->where('data_type_id', $dataType->id)
                       ->first();


            $row_details = json_decode($field->details, true);
            $tatoo_options = $row_details['options'];

            $diff = array_diff($request->deportes, array_keys($tatoo_options));
            $diff = array_combine($diff, $diff);


            $row_details['options'] = $tatoo_options + $diff;

            DB::table('data_rows')
              ->where('field', 'deportes')
              ->where('data_type_id', $dataType->id)
              ->update(['details' => json_encode($row_details)]);
        }

        if (!empty($request->cualidades)){
            $field = DB::table('data_rows')
                       ->where('field', 'cualidades')
                       ->where('data_type_id', $dataType->id)
                       ->first();


            $row_details = json_decode($field->details, true);
            $tatoo_options = $row_details['options'];

            $diff = array_diff($request->cualidades, array_keys($tatoo_options));
            $diff = array_combine($diff, $diff);


            $row_details['options'] = $tatoo_options + $diff;

            DB::table('data_rows')
              ->where('field', 'cualidades')
              ->where('data_type_id', $dataType->id)
              ->update(['details' => json_encode($row_details)]);
        }

	    // Check permission
        $this->authorize('add', app($dataType->model_name));
//	    tatuajes

	    // Validate fields with ajax
        $val = $this->validateBread($request->all(), $dataType->addRows);

        if ($val->fails()) {
            return response()->json(['errors' => $val->messages()]);
        }

        if (!$request->ajax()) {
            $data = $this->insertUpdateData($request, $slug, $dataType->addRows, new $dataType->model_name());


            event(new BreadDataAdded($dataType, $data));

            return redirect()
                ->route("voyager.{$dataType->slug}.index")
                ->with([
                        'message'    => __('voyager.generic.successfully_added_new')." {$dataType->display_name_singular}",
                        'alert-type' => 'success',
                    ]);
        }
    }

    //***************************************
    //                _____
    //               |  __ \
    //               | |  | |
    //               | |  | |
    //               | |__| |
    //               |_____/
    //
    //         Delete an item BREA(D)
    //
    //****************************************

    public function destroy(Request $request, $id)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        // Check permission
        $this->authorize('delete', app($dataType->model_name));

        // Init array of IDs
        $ids = [];
        if (empty($id)) {
            // Bulk delete, get IDs from POST
            $ids = explode(',', $request->ids);
        } else {
            // Single item delete, get ID from URL or Model Binding
            $ids[] = $id instanceof Model ? $id->{$id->getKeyName()} : $id;
        }
        foreach ($ids as $id) {
            $data = call_user_func([$dataType->model_name, 'findOrFail'], $id);
            $this->cleanup($dataType, $data);
        }

        $displayName = count($ids) > 1 ? $dataType->display_name_plural : $dataType->display_name_singular;

        $res = $data->destroy($ids);
        $data = $res
            ? [
                'message'    => __('voyager.generic.successfully_deleted')." {$displayName}",
                'alert-type' => 'success',
            ]
            : [
                'message'    => __('voyager.generic.error_deleting')." {$displayName}",
                'alert-type' => 'error',
            ];

        if ($res) {
            event(new BreadDataDeleted($dataType, $data));
        }

        return redirect()->route("voyager.{$dataType->slug}.index")->with($data);
    }

    /**
     * Remove translations, images and files related to a BREAD item.
     *
     * @param \Illuminate\Database\Eloquent\Model $dataType
     * @param \Illuminate\Database\Eloquent\Model $data
     *
     * @return void
     */
    protected function cleanup($dataType, $data)
    {
        // Delete Translations, if present
        if (is_bread_translatable($data)) {
            $data->deleteAttributeTranslations($data->getTranslatableAttributes());
        }

        // Delete Images
        $this->deleteBreadImages($data, $dataType->deleteRows->where('type', 'image'));

        // Delete Files
        foreach ($dataType->deleteRows->where('type', 'file') as $row) {
            $files = json_decode($data->{$row->field});
            if ($files) {
                foreach ($files as $file) {
                    $this->deleteFileIfExists($file->download_link);
                }
            }
        }
    }

    /**
     * Delete all images related to a BREAD item.
     *
     * @param \Illuminate\Database\Eloquent\Model $data
     * @param \Illuminate\Database\Eloquent\Model $rows
     *
     * @return void
     */
    public function deleteBreadImages($data, $rows)
    {
        foreach ($rows as $row) {
            if ($data->{$row->field} != config('voyager.user.default_avatar')) {
                $this->deleteFileIfExists($data->{$row->field});
            }

            $options = json_decode($row->details);

            if (isset($options->thumbnails)) {
                foreach ($options->thumbnails as $thumbnail) {
                    $ext = explode('.', $data->{$row->field});
                    $extension = '.'.$ext[count($ext) - 1];

                    $path = str_replace($extension, '', $data->{$row->field});

                    $thumb_name = $thumbnail->name;

                    $this->deleteFileIfExists($path.'-'.$thumb_name.$extension);
                }
            }
        }

        if ($rows->count() > 0) {
            event(new BreadImagesDeleted($data, $rows));
        }
    }

    // Update AJAX

     public function ajax(Request $request)
    {
        $request = $request->all();
        $query = DB::table('modelos')->select('*');

        foreach ($request as $key => $value) {
            if($key=='altura' || $key=='torax' || $key=='pecho' || $key=='cintura' || $key=='cadera') { 
                $current = explode('-', $value);
                $query->whereBetween($key, [$current[0], $current[1]]);
            }

            if ($key=='sexo' || $key=='pantalon' || $key=='camiseta' || $key=='vestido' || $key=='zapato' || $key=='ojos' || $key=='pelo' || $key=='raza' || $key=='chaqueta') {
                $query->where($key, $value);
            }

            if ($key=='idiomas' || $key=='tatuajes' || $key=='cicatrices' || $key=='piercing' || $key=='deportes') {
                foreach ($value as $val) {
                    $val = json_encode($val, JSON_UNESCAPED_SLASHES);
                    $newval = str_replace("\\", "\\\\", $val);
                    $query->whereRaw("JSON_CONTAINS(".$key.", '[".$newval."]' )")->get();
                }
            }

            if ($key=='edad') {
                if ($value=='0-1') {
                    $fromtime = strtotime("-1 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("today", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='1-3') {
                    $fromtime = strtotime("-3 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-1 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='3-5') {
                    $fromtime = strtotime("-5 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-3 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='5-10') {
                    $fromtime = strtotime("-10 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-5 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='10-15') {
                    $fromtime = strtotime("-15 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-10 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='15-18') {
                    $fromtime = strtotime("-18 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-15 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='18-20') {
                    $fromtime = strtotime("-20 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-18 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='20-25') {
                    $fromtime = strtotime("-25 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-20 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='25-30') {
                    $fromtime = strtotime("-30 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-25 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='30-35') {
                    $fromtime = strtotime("-35 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-30 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='35-40') {
                    $fromtime = strtotime("-40 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-35 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='40-45') {
                    $fromtime = strtotime("-45 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-40 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='45-50') {
                    $fromtime = strtotime("-50 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-45 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='50-55') {
                    $fromtime = strtotime("-55 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-50 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='55-60') {
                    $fromtime = strtotime("-60 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-55 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='60-65') {
                    $fromtime = strtotime("-65 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-60 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='65-75') {
                    $fromtime = strtotime("-75 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-65 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }
                if ($value=='75-99') {
                    $fromtime = strtotime("-99 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-75 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));  
                }

            }

            if ($key='modeltype') {
                if ($value == 'adulto') {
                    $fromtime = strtotime("-99 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("-18 year", time());
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));
                } elseif ($value == 'niño') {
                    $fromtime = strtotime("-18 year", time());
                    $from = date("Y-m-d", $fromtime);
                    $totime = strtotime("now");
                    $to = date("Y-m-d", $totime);
                    $query->whereBetween('fecha_nacimiento', array($from, $to));
                }
            }

            if ($key='mc') {
                if ($value == 'modelo') {
                    $query->where('tipo', 'Modelo');
                } elseif ($value == 'comercial') {
                    $query->where('tipo', 'Comercial');
                }
            }

        }
        $response = $query->get();
        return response()->json(['success'=>$response]);
    }

// Export PDF

     public function pdf(Request $request)
    {
        $slug = 'modelos';

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        // Compatibility with Model binding.
        //$id = $id instanceof Model ? $id->{$id->getKeyName()} : $id;

        $relationships = $this->getRelationships($dataType);
        // if (strlen($dataType->model_name) != 0) {
        //     $model = app($dataType->model_name);
        //     $dataTypeContent = call_user_func([$model->with($relationships), 'findOrFail'], $id);
        // } else {
        //     // If Model doest exist, get data from table name
        //     $dataTypeContent = DB::table($dataType->name)->where('id', $id)->first();
        // }

        // Replace relationships' keys for labels and create READ links if a slug is provided.
        // $dataTypeContent = DB::table($dataType->name)->where('id', $id)->first();
        // $dataTypeContent = $this->resolveRelations($dataTypeContent, $dataType, true);
        $modelos =  (array) $request->modelos;
        $predataTypeContent = DB::table($dataType->name)->whereIn('id', $request->modelos)
                    ->get();
        // // If a column has a relationship associated with it, we do not want to show that field
        // $this->removeRelationshipField($dataType, 'read');

        // // Check permission
        // $this->authorize('read', $dataTypeContent);

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($predataTypeContent);



        // $view = 'voyager::bread.pdf-modelos';

        // if (view()->exists("voyager::$slug.pdf-modelos")) {
        //     $view = "voyager::$slug.pdf-modelos";
        // }

        //return Voyager::view('voyager::bread.pdf-modelos', compact('dataType', 'dataTypeContent', 'isModelTranslatable'));

        $pdf = PDF::loadView('voyager::bread.pdf-modelos-type', compact('dataType', 'predataTypeContent', 'isModelTranslatable'));
        // return $pdf->download('modelo.pdf');
        return $pdf->stream();
        //return response()->json(['success'=>$request->modelos]);

    }

// Save Package

    public function package(Request $request)
    {
        $modelos =  (array) $request->modelos;
        $modelos_json = json_encode($modelos);
        DB::table('packages')->insert(['nombre'=> $request->packagename,'modelos' => $modelos_json, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        
        return response()->json(['success'=>'Ok']);

    }

    public function addpackage(Request $request)
    {
        $modelos =  (array) $request->modelos;

        $package = DB::table('packages')->select('*')->where('id',$request->packageid)->first();
        $current = $package->modelos;
        $modelosbefore = json_decode($current);
        $modelos = array_merge ($modelos, $modelosbefore);
        $modelos_json = json_encode(array_unique($modelos));

        DB::table('packages')->where('id', $request->packageid)->update(['modelos' => $modelos_json]);
        
        return response()->json(['success'=>'Ok']);

    }

    public function getpackages(Request $request)
    {
        $packages = DB::table('packages')->select('*')->get();
        
        return response()->json(['success'=>$packages]);

    }

     public function idiomas(Request $request)
    {
        $slug = 'modelos';
        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();
 
        $view = 'voyager::bread.idiomas';
 
 
        $idiomas = DB::table('data_rows')
                      ->where('field', 'idiomas')
                      ->where('data_type_id', $dataType->id)
                      ->first();
        if ($idiomas){
            $idiomas = json_decode($idiomas->details);
            $idiomas = $idiomas->options;
        }
 
        return Voyager::view($view, compact(
            'idiomas',
            'dataType'
        ));
 
    }
 
    public function deleteidiomas( $id ) {
        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', 'modelos')->first();
 
        $field = DB::table('data_rows')
                   ->where('field', 'idiomas')
                   ->where('data_type_id', $dataType->id)
                   ->first();
 
        if (!$field){
            return back();
        }
 
        $row_details = json_decode($field->details, true);
        $idioma_options = $row_details['options'];
 
        if (isset($idioma_options[$id])){
            unset($idioma_options[$id]);
            $row_details['options'] = $idioma_options;
 
            DB::table('data_rows')
              ->where('field', 'idiomas')
              ->where('data_type_id', $dataType->id)
              ->update(['details' => json_encode($row_details)]);
        }
 
        return back()->with('success', 'El Idiomas ha sido eliminado con éxito');
 
    }

    public function tatuajes(Request $request)
    {
        $slug = 'modelos';
        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();
 
        $view = 'voyager::bread.tatuajes';
 
 
        $tatuajes = DB::table('data_rows')
                      ->where('field', 'tatuajes')
                      ->where('data_type_id', $dataType->id)
                      ->first();
        if ($tatuajes){
            $tatuajes = json_decode($tatuajes->details);
            $tatuajes = $tatuajes->options;
        }
 
        return Voyager::view($view, compact(
            'tatuajes',
            'dataType'
        ));
 
    }
 
    public function deletetatuajes( $id ) {
        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', 'modelos')->first();
 
        $field = DB::table('data_rows')
                   ->where('field', 'tatuajes')
                   ->where('data_type_id', $dataType->id)
                   ->first();
 
        if (!$field){
            return back();
        }
 
        $row_details = json_decode($field->details, true);
        $tatoo_options = $row_details['options'];
 
        if (isset($tatoo_options[$id])){
            unset($tatoo_options[$id]);
            $row_details['options'] = $tatoo_options;
 
            DB::table('data_rows')
              ->where('field', 'tatuajes')
              ->where('data_type_id', $dataType->id)
              ->update(['details' => json_encode($row_details)]);
        }
 
        return back()->with('success', 'El tatuaje ha sido eliminado con éxito');
 
    }

    public function cicatrices(Request $request)
    {
        $slug = 'modelos';
        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();
 
        $view = 'voyager::bread.cicatrices';
 
 
        $cicatrices = DB::table('data_rows')
                      ->where('field', 'cicatrices')
                      ->where('data_type_id', $dataType->id)
                      ->first();
        if ($cicatrices){
            $cicatrices = json_decode($cicatrices->details);
            $cicatrices = $cicatrices->options;
        }
 
        return Voyager::view($view, compact(
            'cicatrices',
            'dataType'
        ));
 
    }
 
    public function deletecicatrices( $id ) {
        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', 'modelos')->first();
 
        $field = DB::table('data_rows')
                   ->where('field', 'cicatrices')
                   ->where('data_type_id', $dataType->id)
                   ->first();
 
        if (!$field){
            return back();
        }
 
        $row_details = json_decode($field->details, true);
        $cicatrices_options = $row_details['options'];
 
        if (isset($cicatrices_options[$id])){
            unset($cicatrices_options[$id]);
            $row_details['options'] = $cicatrices_options;
 
            DB::table('data_rows')
              ->where('field', 'cicatrices')
              ->where('data_type_id', $dataType->id)
              ->update(['details' => json_encode($row_details)]);
        }
 
        return back()->with('success', 'La cicatris ha sido eliminado con éxito');
 
    }

    public function piercings(Request $request)
    {
        $slug = 'modelos';
        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();
 
        $view = 'voyager::bread.piercings';
 
 
        $piercings = DB::table('data_rows')
                      ->where('field', 'piercings')
                      ->where('data_type_id', $dataType->id)
                      ->first();
        if ($piercings){
            $piercings = json_decode($piercings->details);
            $piercings = $piercings->options;
        }
 
        return Voyager::view($view, compact(
            'piercings',
            'dataType'
        ));
 
    }
 
    public function deletepiercings( $id ) {
        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', 'modelos')->first();
 
        $field = DB::table('data_rows')
                   ->where('field', 'piercings')
                   ->where('data_type_id', $dataType->id)
                   ->first();
 
        if (!$field){
            return back();
        }
 
        $row_details = json_decode($field->details, true);
        $piercings_options = $row_details['options'];
 
        if (isset($piercings_options[$id])){
            unset($piercings_options[$id]);
            $row_details['options'] = $piercings_options;
 
            DB::table('data_rows')
              ->where('field', 'piercings')
              ->where('data_type_id', $dataType->id)
              ->update(['details' => json_encode($row_details)]);
        }
 
        return back()->with('success', 'El piercings ha sido eliminado con éxito');
 
    }

    public function deportes(Request $request)
    {
        $slug = 'modelos';
        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();
 
        $view = 'voyager::bread.deportes';
 
 
        $deportes = DB::table('data_rows')
                      ->where('field', 'deportes')
                      ->where('data_type_id', $dataType->id)
                      ->first();
        if ($deportes){
            $deportes = json_decode($deportes->details);
            $deportes = $deportes->options;
        }
 
        return Voyager::view($view, compact(
            'deportes',
            'dataType'
        ));
 
    }
 
    public function deletedeportes( $id ) {
        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', 'modelos')->first();
 
        $field = DB::table('data_rows')
                   ->where('field', 'deportes')
                   ->where('data_type_id', $dataType->id)
                   ->first();
 
        if (!$field){
            return back();
        }
 
        $row_details = json_decode($field->details, true);
        $deportes_options = $row_details['options'];
 
        if (isset($deportes_options[$id])){
            unset($deportes_options[$id]);
            $row_details['options'] = $deportes_options;
 
            DB::table('data_rows')
              ->where('field', 'deportes')
              ->where('data_type_id', $dataType->id)
              ->update(['details' => json_encode($row_details)]);
        }
 
        return back()->with('success', 'El deporte ha sido eliminado con éxito');
 
    }

}

