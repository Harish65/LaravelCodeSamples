<?php

namespace App\Http\Controllers\Dashboard;

use App\Opportunity;
use App\Role;
use App\SystemText;
use App\VideoNote;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

class VideosController extends Controller
{

    public function videos_management()
    {
        $home_videos = SystemText::where('type', 'multiple-url')->where('name', 'front.home.url.video')->get()->toarray();
        $rent_page_videos = SystemText::where('type', 'multiple-url')->where('name', 'front.rent.url.video')->get()->toarray();
        $mentor_page_videos = SystemText::where('type', 'multiple-url')->where('name', 'front.mentor.url.video')->get()->toarray();

        $sell_page_videos = SystemText::where('type', 'multiple-url')->where('name', 'front.sell.url.video')->get()->toarray();
        $army_page_videos = SystemText::where('type', 'multiple-url')->where('name', 'front.army.url.video')->get()->toarray();

        $agent_page_videos = SystemText::where('type', 'multiple-url')->where('name', 'front.plus.url.video')->get()->toarray();
        $buy_page_videos = SystemText::where('type', 'multiple-url')->where('name', 'front.buy.url.video')->get()->toarray();

        $generate_sales_videos = SystemText::where('type', 'multiple-url')->where('name', 'back.listings.generate.sales.video')->get()->toarray();
        $my_listings_videos = SystemText::where('type', 'multiple-url')->where('name', 'back.listings.index.video')->get()->toarray();


        $data = array('home_videos' => $home_videos, 'rent_page_videos' => $rent_page_videos, 'mentor_page_videos' => $mentor_page_videos, 'sell_page_videos' => $sell_page_videos, 'army_page_videos' => $army_page_videos, 'agent_page_videos' => $agent_page_videos, 'buy_page_videos' => $buy_page_videos, 'generate_sales_videos' => $generate_sales_videos, 'my_listings_videos' => $my_listings_videos);

        
        return view('dashboard.layout.videos_management.index')->with($data);
    }

    /**
     * Edit opportunity pages by given id
     *
     * @param int $id
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $opportunity = Opportunity::find($id);
        $system_text_url = SystemText::find($id);

        if (!$system_text_url) {
            abort(404);
        }

        return view('dashboard.layout.videos_management.edit', compact('system_text_url', 'id'));
    }


    /**
     * Update video by id
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
//        $this->validate(
//            $request,
//            [
//                'video_0' => 'required',
//                'video_1' => 'required',
//                'video_2' => 'required',
//                'video_3' => 'required',
//                'video_4' => 'required',
//                'video_5' => 'required',
//                'video_6' => 'required',
//                'video_7' => 'required',
//                'video_8' => 'required',
//                'video_9' => 'required'
//            ],
//            ['system_text.required' => 'Please enter 10 video urls.']
//        );


        $system_text = SystemText::find($id);

        if (!$system_text) {
            abort(500);
        }

        $new_text_value = '';
        foreach ($request->all() as $key => $video){
            if($key !== '_token' && !str_contains($key, 'video_text')){
                if(empty($video)) $video = '';
                $video = trim($video);
                if($video !== ''){
                    $new_text_value .= $video . ';';
                }
            } else if(str_contains($key, 'video_text')){
                $video_text = trim($video);

                
                $index = intval(str_replace('video_text_', '', $key));

                $video_note = VideoNote::where([['video_index', $index],['system_text_id', $id]])->first();

                if(!empty($video_note)){
                    $video_note->note = $video_text;
                    $video_note->save();
                } else {
                    $video_note = new VideoNote();
                    $video_note->video_index = $index;
                    $video_note->note = $video_text;
                    $video_note->system_text_id = $id;
                    $video_note->save();
                }



            }
        }
        $new_text_value = substr($new_text_value, 0, -1);
        

        

//        $new_text_value =  $request->video_0 . ';' . $request->video_1 . ';' . $request->video_2 . ';' . $request->video_3 . ';' . $request->video_4 . ';' . $request->video_5 . ';' . $request->video_6 . ';' . $request->video_7 . ';' . $request->video_8 . ';' . $request->video_9;

        $system_text->text = $new_text_value;
        $system_text->save();

        return redirect()->route('sections.videos_management');
    }

    public function updateVideosOrder(Request $request, $id){
        $new_string = $request->get('new_string');

        $system_text = SystemText::find($id);
        $system_text->text = $new_string;
        $system_text->save();

        return response(['message' => 'Success']);

    }

    public function save_order(Request $request) {
        $system_text_id = $request->id;
        $text_new_order = $request->new_order;
        $text_new_order = substr($text_new_order, 0, -1);

        $system_text = SystemText::find($system_text_id);
        $system_text->text = $text_new_order;
        $system_text->save();
    }


}
