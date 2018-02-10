/**
 * Created by huygo on 2018/1/3.
 */
import cm_cookie from './com_cookie'
let lockReconnect = false;  //避免ws重复连接
let ws = null;          // 判断当前浏览器是否支持WebSocket
let newMsg_num = 0;
window.w_chat = [];
// createWebSocket(wsUrl);   //连接ws
const wsUrl = `ws://${window.socketConfig.host_chat}:${window.socketConfig.port_chat}`;
const ws_obj = {
  clear_newNum: function () {
    newMsg_num = 0
  },
  test: function () {
    newMsg_num = 99
  },
  close_ws: function () {
    ws.close();
  },
  send_ws: function (msg_content) {
    ws.send(msg_content);
  },
  createWebSocket: function (page, context) {
    try {
      if ('WebSocket' in window) {
        ws = new WebSocket(wsUrl);
        window.w_chat.push(ws);
        for (let i = 0; i < window.w_chat.length - 1; i++) {
          window.w_chat[i].close()
        }
      } else if ('MozWebSocket' in window) {
        ws = new MozWebSocket(wsUrl);
        window.w_chat.push(ws);
        for (let i = 0; i < window.w_chat.length - 1; i++) {
          window.w_chat[i].close()
        }
      } else {
        console.log("您的浏览器不支持websocket协议,建议使用新版谷歌、火狐等浏览器，请勿使用IE10以下浏览器，360浏览器请使用极速模式，不要使用兼容模式！");
      }
      ws_obj.initEventHandle(page, context);
    } catch (e) {
      // ws_obj.reconnect(wsUrl);
      console.log(e);
    }
  },
  initEventHandle: function (page, context) {
    const u_name = cm_cookie.getCookie('user_name');
    const my_config = {
      cmd: 'join',
      fc_type: page,
      uid: '-1',
      username: u_name,
      sendtime: new Date().toLocaleString()
    };
    const sendParam = JSON.stringify(my_config);
    ws.onclose = function () {
      // ws_obj.reconnect(wsUrl);
      console.log("llws连接关闭!" + new Date().toUTCString());
    };
    ws.onerror = function () {
      // ws_obj.reconnect(wsUrl);
      console.log("llws连接错误!");
      // context.$Modal.warning({
      //   content: '抱歉！聊天室连接错误,点击确定刷新页面重新连接,如果问题依旧请反馈给我们，谢谢',
      //   onOk: () => {
      //     context.$router.go(0);
      //   }
      // });
    };
    // context.$Modal.warning({
    //   content: '您的网络断开了,聊天室连接已断开,请检查您的网络是否已连接互联网,点击确定刷新页面重新连接',
    //   onOk:()=>{
    //     context.$router.go(0);
    //   }
    // });
    ws.onopen = function () {
      ws.send(sendParam);
      console.log("llws连接成功!" + new Date().toUTCString());
    };
    ws.onmessage = function (event) {    //获取到消息，
      console.log("llws收到消息啦");
      // console.log(event);
      let back_data = JSON.parse(event.data);
      if (back_data.cmd == 'ping') {
        ws.send('{"cmd":"pong"}');
      } else if (back_data.cmd == 'join') {
        context.$root.$emit('get_msgContent', back_data)
      } else if (back_data.cmd == 'send') {
        if (back_data.data.uid != 1) {
          newMsg_num += 1;
        }
        context.$root.$emit('get_msgContent', back_data.data, newMsg_num);
      } else if (back_data.cmd == 'exit') {
        context.$root.$emit('get_msgContent', back_data)
      }
    };
  },
  reconnect: function (url) {
    if (lockReconnect) return;
    lockReconnect = true;
    setTimeout(function () {     //没连接上会一直重连，设置延迟避免请求过多,暂时用不到
      ws_obj.createWebSocket(url);
      lockReconnect = false;
    }, 2000);
  },
};
export default ws_obj;