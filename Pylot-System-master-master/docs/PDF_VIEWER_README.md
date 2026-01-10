# PDF Viewer Integration for PYlot System

This document describes the enhanced PDF viewing capabilities integrated into your PYlot system, supporting multiple PDF viewer options including Foxit, Adobe, and built-in viewers.

## Features

### Multiple PDF Viewer Options

1. **React PDF Viewer (Built-in)**
   - Custom PDF viewer using `react-pdf` and `pdfjs-dist`
   - Advanced controls: zoom, rotation, page navigation
   - Responsive design with mobile support
   - Error handling with fallback options

2. **Foxit PDF Viewer**
   - Professional PDF viewer via Foxit's web viewer
   - Advanced annotation and markup tools
   - High-quality rendering
   - Cloud-based processing

3. **Adobe PDF Viewer**
   - Adobe's cloud-based PDF viewer
   - Professional-grade features
   - Seamless integration with Adobe ecosystem
   - Reliable rendering across devices

4. **Browser PDF Viewer**
   - Uses browser's native PDF viewing capabilities
   - Fast loading and rendering
   - No additional dependencies
   - Consistent with user's browser preferences

## Implementation

### Components

- **`PDFViewer.jsx`**: Main PDF viewer component with viewer selection
- **`PDFViewer.css`**: Styling for all PDF viewer options
- **`PDFViewerDemo.jsx`**: Demo component showcasing all viewer options

### Integration Points

1. **Admin Module Management** (`Modules_admin.jsx`)
   - Upload and manage PDF modules
   - View PDFs with all viewer options
   - Download and delete functionality

2. **User Module Access** (`Modules.jsx`)
   - Browse available PDF modules
   - View PDFs with preferred viewer
   - Download modules for offline access

## Usage

### Basic Usage

```jsx
import PDFViewer from './components/PDFViewer';

<PDFViewer
  pdfUrl="https://example.com/document.pdf"
  fileName="Document Name"
  onClose={() => setShowViewer(false)}
  onDownload={() => handleDownload()}
  viewerType="react-pdf" // 'react-pdf', 'foxit', 'adobe', 'browser'
/>
```

### Viewer Types

- `react-pdf`: Built-in React PDF viewer with full controls
- `foxit`: Foxit PDF viewer (requires internet connection)
- `adobe`: Adobe PDF viewer (requires internet connection)
- `browser`: Browser's native PDF viewer

### Props

| Prop | Type | Description |
|------|------|-------------|
| `pdfUrl` | string | URL of the PDF file to display |
| `fileName` | string | Display name for the PDF |
| `onClose` | function | Callback when viewer is closed |
| `onDownload` | function | Callback when download is requested |
| `viewerType` | string | Initial viewer type (optional, defaults to 'react-pdf') |

## Features by Viewer Type

### React PDF Viewer
- ✅ Zoom in/out (50% - 300%)
- ✅ Page navigation
- ✅ Rotation (0°, 90°, 180°, 270°)
- ✅ Reset view
- ✅ Download
- ✅ Open in new tab
- ✅ Error handling with fallback options
- ✅ Responsive design

### Foxit PDF Viewer
- ✅ Professional PDF viewing
- ✅ Annotation tools
- ✅ High-quality rendering
- ✅ Cloud-based processing
- ✅ Download
- ✅ Open in new tab
- ⚠️ Requires internet connection

### Adobe PDF Viewer
- ✅ Adobe's professional viewer
- ✅ Cloud-based processing
- ✅ Reliable rendering
- ✅ Download
- ✅ Open in new tab
- ⚠️ Requires internet connection

### Browser PDF Viewer
- ✅ Native browser support
- ✅ Fast loading
- ✅ User's preferred settings
- ✅ Download
- ✅ Open in new tab
- ✅ No additional dependencies

## Styling

The PDF viewer includes comprehensive CSS styling with:

- Responsive design for mobile and desktop
- Modern UI with clean aesthetics
- Smooth animations and transitions
- Accessible color schemes
- Mobile-optimized controls

## Error Handling

The PDF viewer includes robust error handling:

1. **Load Errors**: If a PDF fails to load, fallback options are provided
2. **Network Issues**: Graceful degradation for external viewers
3. **Browser Compatibility**: Fallback to browser viewer if needed
4. **User Feedback**: Clear error messages and recovery options

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Dependencies

- `react-pdf`: ^10.1.0
- `pdfjs-dist`: ^5.4.149
- React 19.1.0+

## Security Considerations

- PDFs are served through your backend API
- CORS headers are properly configured
- No PDF content is stored in browser cache
- External viewers use HTTPS URLs only

## Performance

- Lazy loading of PDF content
- Efficient memory management
- Optimized rendering for large PDFs
- Responsive loading states

## Future Enhancements

- PDF annotation support
- Search within PDF functionality
- Print capabilities
- Full-screen mode
- PDF comparison tools
- Custom viewer themes

## Troubleshooting

### Common Issues

1. **PDF not loading**: Check network connection and PDF URL
2. **External viewers not working**: Ensure internet connection
3. **Mobile display issues**: Use browser viewer for best compatibility
4. **Slow loading**: Try different viewer types

### Debug Mode

Enable debug logging by setting:
```javascript
localStorage.setItem('pdfViewerDebug', 'true');
```

## Support

For technical support or feature requests, please contact your development team or refer to the project documentation.
