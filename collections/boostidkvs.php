<?php

return function ($site) {
    $kv = [];
    foreach (\Bnomei\BoostIndex::singleton()->toArray() as $boostid => $id) {
        $kv[] = [
            'value' => $boostid,
            'text' => A::last(explode(\Bnomei\BoostIndex::SEPERATOR, $id)),
            'diruri' => A::first(explode(\Bnomei\BoostIndex::SEPERATOR, $id)),
        ];
    }
    usort($kv, function ($a, $b) {
        if ($a['diruri'] == $b['diruri']) {
            return 0;
        }
        return ($a['diruri'] < $b['diruri']) ? -1 : 1;
    });
    $kv = array_map(function ($item) {
        return new \Kirby\Toolkit\Obj($item);
    }, $kv);
    return $kv;
};
