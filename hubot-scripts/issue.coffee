# Description:
#   creates a git issue related to the current channel.
#
# Notes:
# passes room id to external script so it can search for the right repo
# Commands:
#   make an issue DESCRIPTIVE TEXT - creates a git issue related to the current channel


module.exports = (robot) ->

  robot.respond /make an issue (.*)/i, (msg) ->
    cmd = "php/handler_issue";
    args = [msg.message.room, msg.match[0] ]
    run_cmd cmd, args, (text) -> msg.send text.replace("\n","");


