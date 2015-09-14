# This is an example of a Ganglia Meta Daemon configuration file
#                http://ganglia.sourceforge.net/
#
#
#-------------------------------------------------------------------------------
# Setting the debug_level to 1 will keep daemon in the forground and
# show only error messages. Setting this value higher than 1 will make 
# gmetad output debugging information and stay in the foreground.
# default: 0
# debug_level 10
#
#-------------------------------------------------------------------------------
# What to monitor. The most important section of this file. 
#
# The data_source tag specifies either a cluster or a grid to
# monitor. If we detect the source is a cluster, we will maintain a complete
# set of RRD databases for it, which can be used to create historical 
# graphs of the metrics. If the source is a grid (it comes from another gmetad),
# we will only maintain summary RRDs for it.
#
# Format: 
# data_source "my cluster" [polling interval] address1:port addreses2:port ...
# 
# The keyword 'data_source' must immediately be followed by a unique
# string which identifies the source, then an optional polling interval in 
# seconds. The source will be polled at this interval on average. 
# If the polling interval is omitted, 15sec is asssumed. 
#
# If you choose to set the polling interval to something other than the default,
# note that the web frontend determines a host as down if its TN value is less
# than 4 * TMAX (20sec by default).  Therefore, if you set the polling interval
# to something around or greater than 80sec, this will cause the frontend to
# incorrectly display hosts as down even though they are not.
#
# A list of machines which service the data source follows, in the 
# format ip:port, or name:port. If a port is not specified then 8649
# (the default gmond port) is assumed.
# default: There is no default value
#
# data_source "my cluster" 10 localhost  my.machine.edu:8649  1.2.3.5:8655
# data_source "my grid" 50 1.3.4.7:8655 grid.org:8651 grid-backup.org:8651
# data_source "another source" 1.3.4.7:8655  1.3.4.8

%%%DATASOURCELIST%%%

#
# Round-Robin Archives
# You can specify custom Round-Robin archives here (defaults are listed below)
#
# Old Default RRA: Keep 1 hour of metrics at 15 second resolution. 1 day at 6 minute
# RRAs "RRA:AVERAGE:0.5:1:244" "RRA:AVERAGE:0.5:24:244" "RRA:AVERAGE:0.5:168:244" "RRA:AVERAGE:0.5:672:244" \
#      "RRA:AVERAGE:0.5:5760:374"
# New Default RRA
# Keep 5856 data points at 15 second resolution assuming 15 second (default) polling. That's 1 day
# Two weeks of data points at 1 minute resolution (average)
#RRAs "RRA:AVERAGE:0.5:1:5856" "RRA:AVERAGE:0.5:4:20160" "RRA:AVERAGE:0.5:40:52704"

#
#-------------------------------------------------------------------------------
# Scalability mode. If on, we summarize over downstream grids, and respect
# authority tags. If off, we take on 2.5.0-era behavior: we do not wrap our output
# in <GRID></GRID> tags, we ignore all <GRID> tags we see, and always assume
# we are the "authority" on data source feeds. This approach does not scale to
# large groups of clusters, but is provided for backwards compatibility.
# default: on
# scalable off
#
#-------------------------------------------------------------------------------
# The name of this Grid. All the data sources above will be wrapped in a GRID
# tag with this name.
# default: unspecified
# gridname "MyGrid"
#
#-------------------------------------------------------------------------------
# The authority URL for this grid. Used by other gmetads to locate graphs
# for our data sources. Generally points to a ganglia/
# website on this machine.
# default: "http://hostname/ganglia/",
#   where hostname is the name of this machine, as defined by gethostname().
# authority "http://mycluster.org/newprefix/"
#
#-------------------------------------------------------------------------------
# List of machines this gmetad will share XML with. Localhost
# is always trusted. 
# default: There is no default value
# trusted_hosts 127.0.0.1 169.229.50.165 my.gmetad.org
#
#-------------------------------------------------------------------------------
# If you want any host which connects to the gmetad XML to receive
# data, then set this value to "on"
# default: off
# all_trusted on
#
#-------------------------------------------------------------------------------
# If you don't want gmetad to setuid then set this to off
# default: on
# setuid off
#
#-------------------------------------------------------------------------------
# User gmetad will setuid to (defaults to "nobody")
# default: "nobody"
# setuid_username "nobody"
#
#-------------------------------------------------------------------------------
# Umask to apply to created rrd files and grid directory structure
# default: 0 (files are public)
# umask 022
#
#-------------------------------------------------------------------------------
# The port gmetad will answer requests for XML
# default: 8651
# xml_port 8651
#
#-------------------------------------------------------------------------------
# The port gmetad will answer queries for XML. This facility allows
# simple subtree and summation views of the XML tree.
# default: 8652
# interactive_port 8652
#
#-------------------------------------------------------------------------------
# The number of threads answering XML requests
# default: 4
# server_threads 10
#
#-------------------------------------------------------------------------------
# Where gmetad stores its round-robin databases
# default: "/var/lib/ganglia/rrds"
# rrd_rootdir "/some/other/place"
#
#-------------------------------------------------------------------------------
# List of metric prefixes this gmetad will not summarize at cluster or grid level.
# default: There is no default value
# unsummarized_metrics diskstat CPU
#
#-------------------------------------------------------------------------------
# In earlier versions of gmetad, hostnames were handled in a case
# sensitive manner
# If your hostname directories have been renamed to lower case,
# set this option to 0 to disable backward compatibility.
# From version 3.2, backwards compatibility will be disabled by default.
# default: 1   (for gmetad < 3.2)
# default: 0   (for gmetad >= 3.2)
case_sensitive_hostnames 0

#-------------------------------------------------------------------------------
# It is now possible to export all the metrics collected by gmetad directly to
# graphite by setting the following attributes. 
#
# The hostname or IP address of the Graphite server
# default: unspecified
# carbon_server "my.graphite.box"
#
# The port and protocol on which Graphite is listening
# default: 2003
# carbon_port 2003
#
# default: tcp
# carbon_protocol udp
#
# **Deprecated in favor of graphite_path** A prefix to prepend to the 
# metric names exported by gmetad. Graphite uses dot-
# separated paths to organize and refer to metrics. 
# default: unspecified
# graphite_prefix "datacenter1.gmetad"
#
# A user-definable graphite path. Graphite uses dot-
# separated paths to organize and refer to metrics. 
# For reverse compatibility graphite_prefix will be prepended to this
# path, but this behavior should be considered deprecated.
# This path may include 3 variables that will be replaced accordingly:
# %s -> source (cluster name)
# %h -> host (host name)
# %m -> metric (metric name)
# default: graphite_prefix.%s.%h.%m
# graphite_path "datacenter1.gmetad.%s.%h.%m

# Number of milliseconds gmetad will wait for a response from the graphite server 
# default: 500
# carbon_timeout 500

#-------------------------------------------------------------------------------
# Memcached configuration (if it has been compiled in)
# Format documentation at http://docs.libmemcached.org/libmemcached_configuration.html
# default: ""
# memcached_parameters "--SERVER=127.0.0.1"
#

