<?php

namespace App\Http\Controllers\Dashboard;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Pages;

use App\Sections;
use App\SectionCategory;

use App\PagesSections;

class PagesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //

        return view('dashboard.layout.pages.index')->withPages(Pages::paginate(15));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        return view('dashboard.layout.pages.create');
    }

    public function addSection(Request $request)
    {
        //
        return PagesSections::insert(['page_id' => $request->get('page_id'), 'section_id' => $request->get('section_page')]) ? redirect()->back() : view('errors.custom')->withError('sorry');
    }    


    public function removeSection(Request $request)
    {
        return PagesSections::where(['page_id' => $request->get('page_id'), 'section_id' => $request->get('page_section_id')])->delete() ? redirect()->back() : view('errors.custom')->withError('sorry');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        return Pages::insert($request->except('_token')) ? redirect(route('pages.index')) : view('errors.404');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
        return view('dashboard.layout.pages.edit')->withPages(Pages::where('id', $id)->first())->withSections(Sections::where('type_id', '<', 3)->get())->withSectioncategories(SectionCategory::get())->withHeadersections(Sections::where('allpages', 1)->get())->withFootersections(Sections::where('allpages', 2)->get());
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
        return Pages::where('id', $id)->update($request->except('_token')) ? redirect(route('pages.index')) : view('errors.custom')->withError('Sorry error saving the page edit.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
