<?php

# === 思路 ===
# 核心：每次落稳之后截图，根据截图算出棋子的坐标和下一个块顶面的中点坐标，
#      根据两个点的距离乘以一个时间系数获得长按的时间
# 识别棋子：靠棋子的颜色来识别位置，通过截图发现最下面一行大概是一条直线，就从上往下一行一行遍历，
#         比较颜色（颜色用了一个区间来比较）找到最下面的那一行的所有点，然后求个中点，
#         求好之后再让 Y 轴坐标减小棋子底盘的一半高度从而得到中心点的坐标
# 识别棋盘：靠底色和方块的色差来做，从分数之下的位置开始，一行一行扫描，由于圆形的块最顶上是一条线，
#          方形的上面大概是一个点，所以就用类似识别棋子的做法多识别了几个点求中点，
#          这时候得到了块中点的 X 轴坐标，这时候假设现在棋子在当前块的中心，
#          根据一个通过截图获取的固定的角度来推出中点的 Y 坐标
# 最后：根据两点的坐标算距离乘以系数来获取长按时间（似乎可以直接用 X 轴距离）

# TODO: 解决定位偏移的问题
# TODO: 看看两个块中心到中轴距离是否相同，如果是的话靠这个来判断一下当前超前还是落后，便于矫正
# TODO: 一些固定值根据截图的具体大小计算
# TODO: 直接用 X 轴距离简化逻辑

class Pies
{
    protected $under_game_score_y = 300; //截图中刚好低于分数显示区域的 Y 坐标，300 是 1920x1080 的值，2K 屏、全面屏请根据实际情况修改
    protected $press_coefficient = 1.392; //长按的时间系数，请自己根据实际情况调节
    protected $piece_base_height_1_2 = 20; // 二分之一的棋子底座高度，可能要调节
    protected $piece_body_width = 70; //# 棋子的宽度，比截图中量到的稍微大一点比较安全，可能要调节
    protected $swipe = [320, 410, 320, 410]; //初始位置按压的位置
    protected $pic = "./1.png"; //默认图片的位置
    protected $scan_x_border = 0; //边界大小 默认为宽度的 1/8

    protected $piece_x = '';
    protected $piece_y = '';
    protected $board_x = '';
    protected $board_y = '';

    public function __construct($pic = '')
    {
        $this->pic = $pic;
    }
    public function start()
    {
        $this->screenshot();
        $this->im = imagecreatefrompng($this->pic);
        $this->width = imagesx($this->im);
        $this->height = imagesy($this->im);
        $this->scan_x_border = intval($this->width / 8); # 扫描棋子时的左右边界

    }
    /**
     * 利用adb命令 拉取图片
     * @return [type] [description]
     */
    public function screenshot()
    {
        @exec('adb shell screencap -p /sdcard/1.png');
        @exec('adb pull /sdcard/1.png .');
    }

    public function jump($distance)
    {
        $press_time = $distance * $this->press_coefficient;
        $press_time = max($press_time, 200); # 设置 200 ms 是最小的按压时间
        $press_time = intval($press_time);
        $cmd = ' adb shell input swipe 320 410 320 410 ' . $press_time;
        exec($cmd);
        return $press_time;

    }
    /**
     * 算棋子的位置
     * Y轴方向：从分数以下的位置开始往下扫。
     * X轴方向：从边界scan_x_border开始扫
     * @return [type] [description]
     */
    public function findPiece()
    {
        $piece_y_max = 0;
        $piece_x_sum = 0;
        $piece_x_c = 0;
        # 棋子应位于屏幕上半部分，这里暂定不超过2/3
        $beiginY = intval(($this->height) / 3);
        $endY = intval(($this->height) * 2 / 3);
        $beiginX = intval($this->scan_x_border);
        $endX = intval($this->width - $this->scan_x_border);
        for ($y = $beiginY; $y < $endY; $y++) {
            for ($x = $beiginX; $x < $endX; $x++) {
                $pixel = $this->getRGB($x, $y);
                # 根据棋子的最低行的颜色判断，找最后一行那些点的平均值，这个颜色这样应该 OK，暂时不提出来
                # 棋子的rgb在（50,53,90）---（60,63,110）
                if (($pixel[0] > 50 && $pixel[0] < 60) && ($pixel[1] > 53 && $pixel[1] < 63) && ($pixel[2] > 90 && $pixel[2] < 110)) {
                    $piece_x_sum += $x;
                    $piece_x_c += 1;
                    $piece_y_max = max($y, $piece_y_max);
                }

            }
        }

        if (empty($piece_y_max) && empty($piece_x_c)) {
            return [0, 0];
        }

        $data = [];
        $data['piece_x'] = $piece_x_sum / $piece_x_c;

        $data['piece_y'] = $piece_y_max - $this->piece_base_height_1_2; # 上移棋子底盘高度的一半
        $this->piece_x = $data['piece_x'];
        $this->piece_y = $data['piece_y'];
        return $data;
    }

    /**
     * 算落的方块的位置
     * @return [type] [description]
     */
    public function findBoard()
    {
        $beiginY = intval(($this->height) / 3);
        $endY = intval(($this->height) * 2 / 3);
        $beiginX = 0;
        $endX = intval($this->width);
        $board_x = 0;
        $board_y = 0;
        for ($y = $beiginY; $y < $endY; $y++) {
            $last_pixel = $this->getRGB(0, $y);
            $board_x_sum = 0;
            $board_x_c = 0;
            if ($board_x || $board_y) {
                break;
            }
            for ($x = $beiginX; $x < $endX; $x++) {
                $pixel = $this->getRGB($x, $y);
                # 修掉脑袋比下一个小格子还高的情况的 bug
                if (abs($x - $this->piece_x) < $this->piece_body_width) {
                    continue;
                }

                # 修掉圆顶的时候一条线导致的小 bug，这个颜色判断应该 OK，暂时不提出来
                if (abs($pixel[0] - $last_pixel[0]) + abs($pixel[1] - $last_pixel[1]) + abs($pixel[2] - $last_pixel[2]) > 10) {
                    $board_x_sum += $x;
                    $board_x_c += 1;
                }

            }
            if ($board_x_sum) {
                $board_x = $board_x_sum / $board_x_c;
            }

        }
        # 按实际的角度来算，找到接近下一个 board 中心的坐标 这里的角度应该是30°,值应该是tan 30°, math.sqrt(3) / 3
        $board_y = $this->piece_y - abs($board_x - $this->piece_x) * sqrt(3) / 3;
        return ['board_x' => $board_x, 'board_y' => $board_y];
    }

    /**
     * 获取像素点的rgba值
     * @param  [type] $x [description]
     * @param  [type] $y [description]
     * @return [type]    [description]
     */
    public function getRGB($x, $y)
    {
        $rgba = imagecolorat($this->im, $x, $y);
        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;
        $a = ($rgba & 0x7F000000) >> 24;
        return [$r, $g, $b, $a];
    }
    public function __destruct()
    {
        unset($this->im);
    }
}
$pic = new Pies("./1.png");
while (1) {
    $pic->start();
    $pie = $pic->findPiece();
    $board = $pic->findBoard();
    //算距离
    $distances = sqrt(($board['board_x'] - $pie['piece_x']) ** 2 + ($board['board_y'] - $pie['piece_y']) ** 2);
    $time = $pic->jump($distances);
    sleep(1);
}
