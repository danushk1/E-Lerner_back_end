<?php

namespace Database\Seeders;

use App\Models\chemicals;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChemicalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
           $chemicals = [
            ['Mancozeb', 'Broad-spectrum fungicide used to control fungal diseases like blights and spots.', 'blight,leaf spot,fungal'],
            ['Neem Oil', 'Natural pesticide effective against aphids, mites, and mildew.', 'aphid,mildew,mite,pest'],
            ['Copper Oxychloride', 'Fungicide for downy mildew and bacterial blight.', 'downy mildew,bacterial blight'],
            ['Carbendazim', 'Systemic fungicide for anthracnose and powdery mildew.', 'anthracnose,powdery mildew,fungal'],
            ['Chlorpyrifos', 'Insecticide for control of termites, borers, and beetles.', 'termite,borer,beetle,insect'],
            ['Imidacloprid', 'Systemic insecticide against sucking pests like whiteflies.', 'whitefly,aphid,jassid'],
            ['Hexaconazole', 'Fungicide for sheath blight and rust in rice and wheat.', 'rust,sheath blight'],
            ['Cypermethrin', 'Insecticide for bollworms and fruit borers.', 'bollworm,fruit borer,insect'],
            ['Bacillus thuringiensis', 'Biological insecticide effective against caterpillars.', 'caterpillar,larvae'],
            ['Sulphur Dust', 'Fungicide and miticide for powdery mildew.', 'powdery mildew,mites'],
            ['Propiconazole', 'Fungicide for rusts, blights, and leaf spots.', 'rust,blight,leaf spot'],
            ['Fipronil', 'Used for stem borers and root grubs.', 'stem borer,root grub'],
            ['Spinosad', 'Bio-insecticide for thrips and leafminers.', 'thrips,leafminer'],
            ['Dimethoate', 'Effective against aphids and mites.', 'aphid,mite,insect'],
            ['Captan', 'Fungicide for seed and soil-borne diseases.', 'seed rot,damping off'],
            ['Tricyclazole', 'Controls rice blast disease effectively.', 'rice blast'],
            ['Metalaxyl', 'Systemic fungicide for downy mildew and late blight.', 'downy mildew,late blight'],
            ['Thiamethoxam', 'Systemic insecticide for whiteflies and hoppers.', 'whitefly,hoppers'],
            ['Deltamethrin', 'Broad-spectrum pyrethroid for various insects.', 'insect,pest'],
            ['Tebuconazole', 'Fungicide for fruit rot and anthracnose.', 'fruit rot,anthracnose'],
            ['Pendimethalin', 'Herbicide for pre-emergent weed control.', 'weeds'],
            ['Glyphosate', 'Non-selective herbicide for weed control.', 'weeds'],
            ['Atrazine', 'Pre- and post-emergent herbicide for grasses and broadleaf weeds.', 'grass weed,broadleaf'],
            ['Paraquat', 'Contact herbicide for non-crop areas.', 'weeds'],
            ['Pretilachlor', 'Pre-emergent herbicide for rice fields.', 'weeds,rice'],
            ['Flubendiamide', 'Insecticide for lepidopteran pests.', 'lepidoptera,fruit borer'],
            ['Acetamiprid', 'Controls aphids, jassids, and whiteflies.', 'aphid,jassid,whitefly'],
            ['Indoxacarb', 'For caterpillars in cotton and vegetables.', 'caterpillar,cotton pests'],
            ['Emamectin Benzoate', 'Highly effective for bollworms and leaf folders.', 'bollworm,leaf folder'],
            ['Dinotefuran', 'Systemic insecticide for brown planthoppers.', 'planthopper'],
            ['Chlorantraniliprole', 'Controls stem borer and armyworm.', 'stem borer,armyworm'],
            ['Quinalphos', 'Broad-spectrum insecticide.', 'insect,borer,beetle'],
            ['Carbosulfan', 'Used against sucking and chewing insects.', 'sucking insect,chewing pest'],
            ['Phorate', 'Granular insecticide for root and soil insects.', 'root grub,soil pest'],
            ['Monocrotophos', 'Effective against aphids and whiteflies.', 'aphid,whitefly'],
            ['Buprofezin', 'Controls nymphal stages of hoppers.', 'hopper,planthopper'],
            ['Oxydemeton-methyl', 'Systemic insecticide for mites and aphids.', 'mite,aphid'],
            ['Profenofos', 'Insecticide for resistant pests in cotton.', 'cotton pest,bollworm'],
            ['Triazophos', 'For control of borers and aphids.', 'borer,aphid'],
            ['Lambda-cyhalothrin', 'Synthetic pyrethroid for wide pest range.', 'insect,borer'],
            ['Metsulfuron-methyl', 'Selective herbicide for post-emergent broadleaf weed control.', 'broadleaf weed'],
            ['Fenvalerate', 'Insecticide for fruit and pod borers.', 'fruit borer,pod borer'],
            ['Zineb', 'Fungicide for early and late blight.', 'early blight,late blight'],
            ['Validamycin', 'Effective for sheath blight in paddy.', 'sheath blight'],
            ['Kasugamycin', 'Antibiotic fungicide for bacterial leaf blight.', 'bacterial leaf blight'],
            ['Carfentrazone', 'Herbicide for fast weed desiccation.', 'weed'],
            ['Sethoxydim', 'Post-emergent herbicide for grasses.', 'grass'],
            ['Imazethapyr', 'Broad-spectrum herbicide for legumes.', 'legume weed,broadleaf'],
            ['Oxyfluorfen', 'Pre-emergent herbicide for broadleaves and sedges.', 'broadleaf,sedge'],
        ];

        foreach ($chemicals as [$name, $usage, $keywords]) {
            chemicals::create([
                'name' => $name,
                'usage_description' => $usage,
                'disease_keywords' => $keywords,
            ]);
        }
    }
}
