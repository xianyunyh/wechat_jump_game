# 教你用PHP来玩微信跳一跳

代码是根据https://github.com/wangshub/wechat_jump_game 改写的php版本。感谢大神



## 工具介绍

- PHP 5.3+
- gd拓展库
- Adb 驱动，可以到[这里](https://adb.clockworkmod.com/)下载

## 原理说明

1. 将手机点击到《跳一跳》小程序界面；
2. 用Adb 工具获取当前手机截图，并用adb将截图pull上来

```shell
    adb shell screencap -p /sdcard/autojump.png
    adb pull /sdcard/autojump.png .
```

3. 逐列扫描像素点，找到目标位置，棋子位置
4. 用鼠标点击起始点和目标位置，计算像素距离；
5. 根据像素距离，计算按压时间；
6. 用Adb工具点击屏幕蓄力一跳；

```shell
    adb shell input swipe x y x y time(ms)
```

## 安卓手机操作步骤

- 安卓手机打开USB调试，设置》开发者选项》USB调试
- 电脑与手机USB线连接，确保执行`adb devices`可以找到设备id
- 界面转至微信跳一跳游戏，点击开始游戏
- 运行`php wechat_jump.php`，如果手机界面显示USB授权，请点击确认

