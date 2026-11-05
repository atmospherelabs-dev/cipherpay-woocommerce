"""Build the WordPress distribution from the versioned source tree."""
import pathlib, zipfile
root=pathlib.Path(__file__).resolve().parents[1]
output=root/'dist';output.mkdir(exist_ok=True)
with zipfile.ZipFile(output/'cipherpay-for-woocommerce-1.0.3.zip','w',zipfile.ZIP_DEFLATED) as archive:
    for entry in ['cipherpay-for-woocommerce.php','readme.txt','includes','assets']:
        path=root/entry
        for file in sorted(path.rglob('*')) if path.is_dir() else [path]:
            if file.is_file():archive.write(file,pathlib.Path('cipherpay-for-woocommerce')/file.relative_to(root))
print(output/'cipherpay-for-woocommerce-1.0.3.zip')
