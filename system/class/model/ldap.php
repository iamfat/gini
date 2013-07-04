<?php

namespace Model;

class LDAP {

    private $ds;

    protected $options = array();
    
    private $root_binded = false;

    function __construct($opt) {
        
        $this->options = (array) $opt;
                
        $ds = @ldap_connect($this->get_option('host'));
        if (!$ds) {
            throw new \ErrorException('LDAP failed');
        }

        $this->ds = $ds;

        @ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        
        $this->bind_root();
    }
    
    function __destruct() {
        if ($this->ds) {
            @ldap_close($this->ds);
            $this->root_binded = false;
        }
    }
    
    function is_connected() {
        return !!$this->ds;
    }

    function get_option($name, $default=null) {
        if (isset($this->options[$name])) return $this->options[$name];
        return _CONF('ldap.'.$name) ?: $default;
    }
    
    function bind($dn, $password) {
        $ret = @ldap_bind($this->ds, $dn, $password);
        if ($dn != $this->get_option('root_dn')) {
            $this->bind_root();
        }
        return $ret;
    }
    
    private function bind_root() {
        return $this->bind($this->get_option('root_dn'), $this->get_option('root_pass'));
    }
    
    function rename($dn, $dn_new, $base=null, $deleteoldrdn = true) {
        return @ldap_rename($this->ds, $dn, $dn_new, $base, $deleteoldrdn);
    }

    function mod_replace($dn, $data) {
        return @ldap_mod_replace($this->ds, $dn, $data);
    }

    function mod_add($dn, $data) {
        return @ldap_mod_add($this->ds, $dn, $data);
    }

    function mod_del($dn, $data) {
        return @ldap_mod_del($this->ds, $dn, $data);
    }

    function add($dn, $data){
        return @ldap_add($this->ds, $dn, $data);
    }
    
    function modify($dn, $data){
        return @ldap_modify($this->ds, $dn, $data);
    }
    
    function delete($dn){
        return @ldap_delete($this->ds, $dn);
    }
    
    function search() {
        $args = func_get_args();
        array_unshift($args, $this->ds);
        return @call_user_func_array('ldap_search', $args);
    }
    
    function entries($sr) {
        return @ldap_get_entries($this->ds, $sr);
    }
    
    function first_entry($sr) {
        return @ldap_first_entry($this->ds, $sr);
    }
    
    function next_entry($er) {
        return @ldap_next_entry($this->ds, $er);
    }
    
    function entry_dn($er) {
        return @ldap_get_dn($this->ds, $er);
    }
    
    function attributes($er) {
        return @ldap_get_attributes($this->ds, $er);
    }
    
    function set_password($dn, $password) {
        return $this->mod_replace($dn, $this->get_password_attrs($password));
    }
    
    function add_account($base_dn, $account, $password){

        $server_type = $this->get_option('server_type');
        
        switch ($server_type) {
        case 'ads':
            $dn = 'cn='.$account.','.$base_dn;
            $data = array(
                'objectClass' => array('top', 'person', 'organizationalPerson', 'user'),
                'cn' => $account,
                'sAMAccountName' => $account,
            );
            break;
        default:
            $dn = 'cn='.$account.','.$base_dn;
            $data = array(
                'objectClass' => array('top', 'person', 'organizationalPerson', 'posixAccount'),
                'cn' => $account,
                'sn' => $account,
                'uid' => $account,
                'loginShell' => '/bin/false',
                'homeDirectory' => '/home/samba/users/'.$account,
                'uidNumber' => $this->posix_get_new_uid(),
                'gidNumber' => $this->get_option('posix.default_gid'),
            );

            if ($this->get_option('enable_samba3')) {
                $data['objectClass'][] = 'sambaSamAccount';
                $data += array(
                    'sambaSID' => $this->get_option('samba3.SID') . $data['uidNumber'],
                    'sambaPrimaryGroupSID' => $this->get_option('samba3.groupSID', $this->get_option('samba3.SID') . $data['gidNumber']),
                );
            }
            
            if ($this->get_option('enable_shadow')) {
                $data['objectClass'][] = 'shadowAccount';
                $data += array(
                    'shadowExpire' => 99999,
                    'shadowFlag' => 0,
                    'shadowInactive' => 99999,
                    'shadowMax' => 99999,
                    'shadowMin' => 0,
                    'shadowWarning' => 0,
                );
            }
    
            break;
        }
        
        $data += $this->get_password_attrs($password);
        
        $ret = $this->add($dn, $data);
        if ($ret)  $this->enable_account($dn, true);
        return $ret;
    }
    
    function enable_account($dn, $enable=true) {
        switch ($this->get_option('server_type')) {
        case 'ads':
            $sr = $this->search($dn, '(objectClass=*)', array('useraccountcontrol'), true);
            $entries = $this->entries($sr);
            $uac = $entries[0]['useraccountcontrol'][0];
            if ($enable) {
                $uac = $uac & ~0x22;
                $uac = $uac | 0x10000;    //Password never expires
                $this->mod_replace($dn, array('useraccountcontrol' => $uac));
            }
            else {
                //禁用帐号
                if (!($uac & 0x2)) {
                    $this->mod_replace($dn, array('useraccountcontrol' => $uac | 0x2));
                }
            }
            break;
        default:
        }
    }

    private function get_password_attrs($password) {
        switch($this->get_option('pass_algo')) {
        case 'plain':    //不加密
            $secret = $password;
            break;
        case 'md5':
            $secret = '{MD5}'.base64_encode(md5($password, true));
            break;
        case 'sha':
        default:
            $secret = '{SHA}'.base64_encode(sha1($password, true));
            break;
        }
        
        $data = array(
            'userPassword'=> $secret,
        );
        
        if ($this->get_option('enable_samba3')) {
            class_exists('smbHash', false) or Core::load(THIRD_DIR, 'smbhash', '*');
            
            $hash = new smbHash;
            $data['sambaLMPassword']=$hash->lmhash($password);
            $data['sambaNTPassword']=$hash->nthash($password);
        }

        return $data;

    }
    
    private function posix_get_new_uid() {
        static $default_uid = 0;
        if (!$default_uid) $default_uid = $this->get_option('posix.default_uid');
        $account = $default_uid + 1;
        while (posix_getpwuid($account)) {
            $account ++;
        }
        return $default_uid = $account;
    }
        
}
