<?php

class heap extends SplHeap
{

    public function compare($value1, $value2)
    {
        return $value1 > $value2 ? -1 : 1;
    }
}



$heap = new heap();

for ($i=0; $i<20; $i++){
    $heap->insert(random_int(1,100));
}

while ($heap->valid()){
    echo $heap->current().'/n';
    $heap->next();
}
