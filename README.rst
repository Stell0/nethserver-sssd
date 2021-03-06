===============
nethserver-sssd
===============

This package implements authentication and user management layers.
Supported authentication providers are:

* OpenLDAP with POSIX attributes
* Samba or Windows Active Directory

It includes the following parts:

* SSSD configuration
* events for users and  groups management
* web interface for user management
* password expiration notification (not yet implemented)
* system validators for users and groups
* SSSD perl library to ease the implementation of e-smith templates


The implementation can work in two modes:

* read-and-write: if `nethserver-dc` or `nethserver-directory` are installed, the system will
  provide all user management features like creation, modification and deletion of users and groups
* read-only: if users and groups are read from a remote source, the system will
  be able to consume them only using passwd database


Events
------

Defined events are:

user-create
^^^^^^^^^^^

The event creates the user record inside the account provider database.

Parameters:

* username: must be unique
* name: full name of the user
* shell: default to `/usr/libexec/openssh/sftp-server`, if set to `/bin/bash` the user will be able to access the server using SSH


user-modify
^^^^^^^^^^^

The event changes the full name inside the account provider databases

Parameters:

* username
* name: full name of the user
* shell: default to `/usr/libexec/openssh/sftp-server`, if set to `/bin/bash` the user will be able to access the server using SSH

Note: shell option can't be changed for AD users

user-delete
^^^^^^^^^^^

The event deleted the user and remove it from all groups.
Also all data inside the user's home will deleted.

Parameters:

* username


user-lock
^^^^^^^^^

The event locks the user preventing the access.
All new users are in locked state.

Parameters:

* username

user-unlock
^^^^^^^^^^^

The event unlocks the user preventing the access.
This event should be called after the invoking `password-modify` event for the user.

Parameters:

* username


group-create
^^^^^^^^^^^^

The event creates the group record inside the account provider database.

Parameters:

* groupname: must be unique
* members: a list of users member of this group


group-modify
^^^^^^^^^^^^

The event changes the members of a group  inside the account provider database.

Parameters:

* groupname: must be unique
* members: a list of users member of this group



group-delete
^^^^^^^^^^^^

This event deletes a group record from the the account provider database.

Parameters:

* groupname


password-policy-update
^^^^^^^^^^^^^^^^^^^^^^

This event configures password expiration of a single user or of all users.

Parameters

* username (optional)
* passexpires: it can be `yes` or `no`. If user is set and value is `yes`, the user password will expires after a 
  predefined number of days (see `passwordstrength{MaxPassAge}`)

  The duration of a password can be  passwordstrength{MaxPassAge}

System users and groups
-----------------------

SSSD can access all users and groups from an account provider,
but the web interface will always hide system users and groups.

The following users will not be accessible from the web interface:

* all users listed inside `/etc/nethserver/system-users`
* all users with uid < 1000
* all machine accounts from AD

The following groups will not be accessible from the web interface:

* all groups listed inside `/etc/nethserver/system-groups`
* all groups with gid < 1000


NethServer::SSSD
----------------

NethServer::SSSD is the perl library module to retrieve current LDAP configuration. 
It supports both Active Directory and OpenLDAP providers.

Template example: ::

  {
      use NethServer::SSSD;
      my $sssd = new NethServer::SSSD();

      $OUT .= "{ldap_uri, [".$sssd->ldapURI()."]}\n";

      if ($sssd->isAD()) {
          $OUT .= "{ldap_uids, [\"sAMAccountName\"]}.\n";
      }

  }


All functions are documented using perldoc ::

  perldoc NethServer::SSSD


Join Active Directory
---------------------

The Active Directory join operation is run by *realmd*. After the AD has been
joined sucessfully the system keytab file is initialized as long as individual
service keytabs, as defined on the respective *service* record (see `Service
configuration hooks`_).

Service configuration hooks
^^^^^^^^^^^^^^^^^^^^^^^^^^^

A service (i.e. *dovecot*) record in ``configuration`` DB can be extended with
the following special props, that are read during the join operation, machine
password renewal, and crojob tasks: ::

 dovecot=service
    ...    
    KrbStatus=enabled
    KrbCredentialsCachePath=
    KrbKeytabPath=/var/lib/dovecot/krb5.keytab
    KrbPrimaryList=smtp,imap,pop
    KrbKeytabOwner=
    KrbKeytabPerms=

* ``KrbStatus {enabled,disabled}``
  This is the main switch. If set to ``enabled`` a ticket credential cache file is kept valid by the hourly cronjob
* ``KrbCredentialsCachePath``
  The path of the credentials cache. It defaults to ``/tmp/krb5cc*<service*uid>``, if ``service`` is also a system user.
* ``KrbKeytabPath``
  Keytab file path. If empty, ``/var/lib/misc/nsrv-<service>.keytab`` is assumed
* ``KrbPrimaryList <comma separated words list>``
  Defines the keytab contents. In Kerberos jargon a "primary" is the first part of the "principal":http://web.mit.edu/kerberos/krb5-1.5/krb5-1.5.4/doc/krb5-user/What-is-a-Kerberos-Principal*003f.html string, before the slash (``/``) character. Any primary in this list is exported to the keytab.
* ``KrbKeytabOwner``
  The unix file owner. Default is the ``service`` name. This is applied to both the credentials cache file and the keytab file.
* ``KrbKeytabPerms``
  The unix bit permissions in octal form. Default is ``0400``. This is applied to both the credentials cache file and the keytab file.

The implementation is provided by ``/usr/libexec/nethserver/smbads``.

Individual services can link themselves to ``nethserver-sssd-initkeytabs``
action in the respective ``-update`` event.
