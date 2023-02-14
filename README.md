# MiniMe
daloRadius simple add MAC addresses

In my case, I use FreeRadius with NT domain auth throw WinBind with MAC address filter.
I don't need full functional daloRadius, but it's nice to have it just in case.

For simple add users into MySQL, I use this script, that grep FreeRadius log file for connection attempts and allow fast add MAC or MAC&Username into DB.
Username field is placed into Notes.

It's quite enough for me )
