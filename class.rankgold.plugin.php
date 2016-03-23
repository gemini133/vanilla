<?php if (!defined('APPLICATION')) exit();

$PluginInfo['RankGold'] = array(
    'Name' => 'RankGold',
    'Description' => 'vanillaforums plugin for rankgold',
    'Version' => '1.1',
    'Author' => "RankGold",
    'AuthorEmail' => 'none@gmail.com',
    'AuthorUrl' => 'http://members.rankgold.com'
);

class RankGoldPlugin extends Gdn_Plugin {

    public function DiscussionsController_Index_Before($sender)
    {
        $this->do_post_job();
    }

    function get_json_data($string)
    {
        if (!$string || !is_string($string)) return false;

        $array = json_decode($string, true);

        $is_json = is_array($array) && !empty($array);

        if ( $is_json && function_exists('json_last_error'))
            $is_json = (json_last_error() == 0);

        if ($is_json)
            return $array;

        return false;
    }

    function do_post_job()
    {
        $string = file_get_contents('php://input');

        if (!$data = $this->get_json_data($string))
            return false;

        $username = isset($data['username'])?$data['username']:false;
        $password = isset($data['password'])?$data['password']:false;
        if (!$username || !$password)
            exit(json_encode(array('code' => 403, 'message' => 'Credentials not provided!')));

        $user_id = intval($this->auth_user($username, $password));

        if ($user_id === 0)
            exit(json_encode(array('code' => 403, 'message' => 'Credentials are not matched!')));

        if (isset($data['new_post'])) {

            if (!isset($data['post_title']) || !$data['post_title'])
                exit(json_encode(array('code' => 403, 'message' => 'Title not provided!')));

            if (!isset($data['post_content']) || !$data['post_content'])
                exit(json_encode(array('code' => 403, 'message' => 'Content not provided!')));

            if ($id = $this->new_post($data['post_title'], $data['post_content'], $user_id))
                exit(json_encode($id));

            exit(json_encode(array('code' => 500, 'message' => 'Fail to create a post!')));

        } elseif (isset($data['get_post'])) {

            if (!isset($data['id']) || !$data['id'])
                exit(json_encode(array('code' => 403, 'message' => 'id not provided!')));

            if ($info = $this->get_post($data['id']))
                exit(json_encode($info));

            exit(json_encode(array('code' => 403, 'message' => 'Fail to retrieve the id '.$data['id'].'!')));
        }

        exit(json_encode(array('code' => 404, 'message' => 'Fail to retrieve the id '.$data['id'].'!')));

    }

    function get_post($id)
    {

        $model = Gdn::SQL();

        $discussion = $model
           ->GetWhere('Discussion', array('DiscussionID' => $id))
           ->FirstRow(DATASET_TYPE_ARRAY);

        if ($discussion) {
            $array = array();
            $array['post_date'] = $discussion['DateInserted'];
            $array['post_title'] = $discussion['Name'];
            $array['post_id'] = $discussion['DiscussionID'];
            $array['post_link'] = DiscussionUrl($discussion);
            return $array;
        }

        return false;
    }

    function new_post($post_title, $post_content, $user_id)
    {
        $fields = array(
              'CategoryID' => '1',
              'InsertUserID' => $user_id,
              'Name' => $post_title,
              'Body' => $post_content,
              'Format' => 'Html'
        );

        $model = new Gdn_Model('Discussion');
        if ($id = $model->Insert($fields))
            return $id;
        return false;
    }

    function auth_user($username, $password)
    {
        $model = $this->get_user_model();

        $user = $model->ValidateCredentials($username, 0, $password);

        if ($user !== FALSE)
            return $user->UserID;

        return false;

    }

    function get_user_model()
    {
        static $UserModel;

        if (!$UserModel)
            $UserModel = new UserModel();

        return $UserModel;
    }

}
