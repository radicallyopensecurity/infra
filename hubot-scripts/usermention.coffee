# Description:
#   listen to user mentions
#
# Dependencies:
#   None
#
# Configuration:
#   None
#
# Commands:
#   none
#
# Author:
#   johnsinteur
#
# Additional Requirements
#   unix mail client installed on the system


util = require 'util'
child_process = require 'child_process'

module.exports = (robot) ->
  emailTime = null
  sendEmail = (recipients, subject, msg, from) ->
    mailArgs = ['-s', subject, '-a', "From: #{from}", '--']
    mailArgs = mailArgs.concat recipients
    p = child_process.execFile 'mail', mailArgs, {}, (error, stdout, stderr) ->
      util.print 'stdout: ' + stdout
      util.print 'stderr: ' + stderr
    p.stdin.write "#{msg}\n"
    p.stdin.end()

  roomName = (numericname) ->
    switch numericname
# add all numeric names of channels here so we can convert them to the name ppl are used to
      when "YA6GZHk3TCXXNKYDRG" then return "general"


  robot.hear /\*@USERNAME/, (msg) ->
    channelName = roomName msg.message.room
    mailmsg = "You have just been highlighted on ROSchat in #{channelName} by #{msg.message.user.name}: #{msg.message.text}"
    sendEmail "USER@EXAMPLE.com", "ROSchat Notification Email", mailmsg ,  "noreply@EXAMPLE.com"
