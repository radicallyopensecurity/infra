# Description:
# create a chat channel (limited to authorized users)
#
# Notes:
# Commands:
#   make me a channel CHANNELNAME - create a chat channel (limited to authorized users)


module.exports = (robot) ->


  robot.respond /make me a channel (.*)/i, (res) ->
   roomName = res.match[1]
   accesstest = 0;
# expand this to other users as required
   if res.envelope.user.name == "YOURUSERNAME"
     accesstest = 1;
   if accesstest == 0 
     res.send "I am sorry " + res.envelope.user.name + " but I have not been told you are allowed to ask for that"
     return;
   res.send "Sure, hold on"
# add default users to the new channel
   newroom = robot.adapter.callMethod('createPrivateGroup', roomName, ['YOURUSERNAME', 'OTHERDEFAULTUSERNAME'])
   res.send  "+done - Added YOURUSERNAME and OTHERDEFAULTUSERNAME to the new room"
   newroom.then (roomId) =>
   	console.log(roomId)
    robot.messageRoom roomId.rid, "@all hello!"
