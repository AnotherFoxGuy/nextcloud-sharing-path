import { FileAction, Permission, registerFileAction } from '@nextcloud/files'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

import iconSvg from '@mdi/svg/svg/file-link-outline.svg?raw'

const action = new FileAction({
  id: 'copy-sharing-path',
  displayName: () => 'Copy SharingPath',
  iconSvgInline: () => iconSvg,

  // Only works on single files
  enabled(nodes) {
    // Only works on single node
    if (nodes.length !== 1) {
      return false
    }
    return (nodes[0].permissions & Permission.SHARE) !== 0
  },

  async exec(node) {
    console.log(node.path);

    try {
      let prefix = OC.getProtocol() + '://' + OC.getHost() + '/apps/sharingpath/';
      // admin setting
      prefix = settings.default_copy_prefix || prefix;
      prefix = prefix.endsWith('/') ? prefix : (prefix + '/');
      prefix += OC.getCurrentUser().uid;
      // user setting
      prefix = settings.copy_prefix || prefix;
      prefix = prefix.endsWith('/') ? prefix.substring(0, prefix.length - 1) : prefix;

      let path = encodeURI(prefix + node.path);
      navigator.clipboard.writeText(path);

      showSuccess("Copied SharingPath to clipboard")
    } catch (error) {
      showError(error)
    }

    return null
  },

  order: 24,
})

window.addEventListener('DOMContentLoaded', (event) => {
  let settings = {
    default_enabled: '',
    enabled: '',
    default_copy_prefix: '',
    copy_prefix: '',
  };

  $.ajax(generateUrl('/apps/sharingpath/settings'), {
    type: 'GET',
    dataType: 'json',
    success: (data) => {
      settings = data || settings

      if (settings.enabled === 'yes' || (!settings.enabled && settings.default_enabled === 'yes')) {
        registerFileAction(action)
      }
    },
  });
});