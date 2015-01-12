# ros-sshkey

A simple script to keep track of all the ROS volunteers and freelancers SSH keys.

The `keys` file is a plain authorized_keys file with **all** keys in it.

So if you were to deploy an environment where everyone should have access you can copy this
file verbatim.

The `keys.sh` script can be invoked in two ways:

* no arguments, will dump all key identifiers in the keys file
* with arguments, specify one or more key identifiers to get a cherry-picked `authorized_keys` file
