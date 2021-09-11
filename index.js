/*
 *
 */

panel.plugin('bnomei/boost', {
    fields: {
      boostid: {
        props: {
          value: String
        },
        template: '<k-text-field v-model="value" name="boostid" label="BoostID" :disabled="true" />'
      }
    }
  });
