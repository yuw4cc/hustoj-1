<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * all functions about contest
 *
 * @author freefcw
 */

class Model_Contest extends Model_Base
{
    static $cols = array(
        'contest_id',
        'title',
        'start_time',
        'end_time',
        'defunct',
        'description',
        'private',
    );

    private $problem_list = null;

    static $primary_key = 'contest_id';

    static $table = 'contest';

    public $contest_id;
    public $title;
    public $start_time;
    public $end_time;
    public $defunct;
    public $description;
    public $private;

    /**
     * @param string $id
     *
     * @return Model_Contest
     */
    public static function find_by_id($id)
    {
        return parent::find_by_id($id);
    }


    /**
     * 题目列表
     *
     * @param int $num 如果不为null则为第 $num 个题目
     *
     * @return Model_Problem|Model_CPRelation[]
     */
    public function problem($num = NULL)
    {
        if ( is_null($this->problem_list) )
        {
            $this->problem_list = Model_CPRelation::problems_for_contest($this->contest_id);
        }
        if ( is_null($num)  )
            return $this->problem_list;

        $relation = $this->problem_list[$num];
        return $relation->detail();
    }

    /**
     * 题目数量
     *
     * @return int
     */
    public function number_of_problems()
    {
        return count($this->problem());
    }

    /**
     * is contest opened
     * @return bool
     */
    public function is_open()
    {
        $now = time();
        if ( $now > strtotime($this->start_time)  AND $now < strtotime($this->end_time) )
            return true;
        return false;
    }

    public function statistics()
    {
        $solutions = $this->solutions();

        $data = array();
        $lang = array();

        foreach($solutions as $item)
        {
            if (!array_key_exists($item->num, $data)) $data[$item->num] = array();
            if (!array_key_exists($item->result, $data[$item->num])) $data[$item->num][$item->result] = 0;

            if (!array_key_exists($item->num, $lang)) $lang[$item->num] = array();
            if (!array_key_exists($item->language, $lang[$item->num])) $lang[$item->num][$item->language] = 0;

            $data[$item->num][$item->result]++;
            $lang[$item->num][$item->language]++;
        }

        return array('result' => $data, 'language'=>$lang);
    }

    public function solutions()
    {
        return Model_Solution::find_solution_for_contest($this->contest_id);
    }

    public function standing()
    {
        $solutions = $this->solutions();

        /* @var $data Model_Team[] */
        $data = array();
        $start_time = strtotime($this->start_time);
        foreach($solutions as $item)
        {
            if(array_key_exists($item->user_id, $data))
            {
                $team = $data[$item->user_id];
                $team->add($item->num, strtotime($item->in_date) - $start_time, $item->result);
            } else {
                $team = new Model_Team();
                $team->user_id = $item->user_id;
                $team->add($item->num, strtotime($item->in_date) - $start_time, $item->result);
                $data[$item->user_id] = $team;
            }
        }
        usort($data, function($a, $b){
            if ($a->solved > $b->solved)
                return false;
            if ($a->solved == $b->solved) {
                if ($a->time < $b->time) return false;
            };
            return true;
        });
        return $data;
    }

    public function arrange_problem($orderlist)
    {
        Model_CPRelation::empty_contest($this->contest_id);
        $plist = explode(';', $orderlist);
        foreach($plist as $item)
        {
            if ( $item == '') continue;
            list($num, $problem_id) = explode(':', $item);

            $cr = new Model_CPRelation;
            $cr->contest_id = $this->contest_id;
            $cr->problem_id = $problem_id;
            $cr->num = $num;
            $cr->save();
        }
    }

    /**
     * 获取私有比赛的用户
     *
     * @return array
     */
    public function members()
    {
        return Model_Privilege::member_of_contest($this->contest_id);
    }

    public function is_private()
    {
        return intval($this->private) == 1;
    }

    /**
     * @param $user_list
     *
     * @return void
     */
    public function add_member($user_list)
    {
        // TODO: check user
        foreach($user_list as $user_id)
        {
            $perm = new Model_Privilege;
            $perm->user_id = $user_id;
            $perm->rightstr = 'c'.$this->contest_id;
            $perm->save();
        }
    }

    /**
     * @param $user_id
     */
    public function remove_member($user_id)
    {
        $filter = array(
            'user_id' => $user_id,
            'rightstr' => 'c'.$this->contest_id,
        );
        $perm = Model_Privilege::find($filter);

        var_dump($perm);
        foreach($perm as $item)
        {
            $item->defunct = 1;
            $item->destroy();
        }
    }

    public function initial_data()
    {
        $this->title       = '';
        $this->start_time  = '';
        $this->end_time    = '';
        $this->defunct     = 'N';
        $this->description = '';
        $this->private     = 1;
    }

    public function validate()
    {}
}