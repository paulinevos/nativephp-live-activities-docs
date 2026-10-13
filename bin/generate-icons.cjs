// Development only: npm install --prefix .build/icon-tools --no-save @resvg/resvg-js@2.6.2
// Then: node bin/generate-icons.cjs
// Native consumers need neither an SVG renderer nor network access.
const fs = require('node:fs');
const path = require('node:path');
const { Resvg } = require('../.build/icon-tools/node_modules/@resvg/resvg-js');
const root = path.resolve(__dirname, '..');
const names = [...fs.readFileSync(path.join(root, 'src/ActivityIcon.php'), 'utf8')
    .matchAll(/case \w+ = '([^']+)';/g)].map(match => match[1]);
const icons = names.map(name => {
    const svg = fs.readFileSync(path.join(root, 'resources/icons', `${name}.svg`), 'utf8')
        .replace('stroke="currentColor"', 'stroke="#ffffff"');
    const png = new Resvg(svg, { fitTo: { mode: 'width', value: 96 } }).render().asPng();
    return [name, png.toString('base64')];
});
const notice = '// Generated from Lucide 0.468.0 by bin/generate-icons.cjs. See resources/icons/LICENSE.\n';
fs.writeFileSync(path.join(root, 'resources/ios/Sources/LiveActivityIcons.swift'), notice + `import UIKit

enum LiveActivityIcons {
    private static let images: [String: UIImage] = encoded.compactMapValues { value in
        guard let data = Data(base64Encoded: value) else { return nil }
        return UIImage(data: data)
    }

    static func contains(_ name: String) -> Bool { encoded[name] != nil }

    static func image(_ name: String) -> UIImage {
        images[name] ?? images["circle-dashed"] ?? UIImage()
    }

    private static let encoded: [String: String] = [
${icons.map(([name, value]) => `        "${name}": "${value}"`).join(',\n')}
    ]
}
`);
fs.writeFileSync(path.join(root, 'resources/android/src/main/java/com/vos/liveactivities/ActivityIcons.kt'), notice + `package com.vos.liveactivities

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.util.Base64

internal object ActivityIcons {
    private val encoded = mapOf(
${icons.map(([name, value]) => `        "${name}" to "${value}"`).join(',\n')}
    )
    private val images: Map<String, Bitmap> by lazy {
        encoded.mapValues { (_, value) ->
            val bytes = Base64.decode(value, Base64.DEFAULT)
            requireNotNull(BitmapFactory.decodeByteArray(bytes, 0, bytes.size))
        }
    }

    fun contains(name: String): Boolean = encoded.containsKey(name)

    fun bitmap(name: String): Bitmap = images.getValue(name)
}
`);
console.log(`Generated ${icons.length} bundled native icon masks.`);
