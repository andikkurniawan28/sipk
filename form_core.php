<?php

function htmlInputNominalValuasi(string $id, string $label): string
{
    return '<div class="form-group col-md-4"><label>' . h($label) . '</label>'
         . '<input type="number" class="form-control form-control-sm text-right hitung-input" id="' . h($id)
         . '" name="' . h($id) . '" min="0" step="any" value="0"></div>';
}

function htmlTampilanNominalValuasi(string $id, string $label, string $kelasTambahan = ''): string
{
    return '<div class="form-group col-md-4"><label>' . h($label) . '</label>'
         . '<input type="text" class="form-control form-control-sm text-right ' . h($kelasTambahan) . '" id="' . h($id)
         . '" value="0" readonly></div>';
}