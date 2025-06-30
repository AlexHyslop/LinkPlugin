
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { 
    InspectorControls, 
    useBlockProps 
} from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    Button,
    Spinner,
    Placeholder,
    Notice
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { debounce } from '@wordpress/compose';
const PostSearchResults = ({ posts, onSelectPost, loading, isSearchResults }) => {
    if (loading) {
        return <Spinner />;
    }
    
    if (!posts.length) {
        return <p>{isSearchResults 
            ? __('No posts found.', 'stylized-anchor-link')
            : __('No recent posts found.', 'stylized-anchor-link')
        }</p>;
    }
    
    return (
        <div className="stylized-anchor-link-results">
            <h3 className="stylized-anchor-link-results-title">
                {isSearchResults 
                    ? __('Search results', 'stylized-anchor-link') 
                    : __('Recent posts', 'stylized-anchor-link')
                }
            </h3>
            {posts.map(post => (
                <Button
                    key={post.id}
                    isSecondary
                    onClick={() => onSelectPost(post)}
                    className="stylized-anchor-link-result-item"
                >
                    {post.title.rendered} <span className="post-id">#{post.id}</span>
                </Button>
            ))}
        </div>
    );
};

const SimplePagination = ({ currentPage, totalPages, onPageChange }) => {
    if (totalPages <= 1) {
        return null;
    }
    
    return (
        <div className="stylized-anchor-link-pagination">
            <Button 
                isSecondary
                disabled={currentPage <= 1}
                onClick={() => onPageChange(currentPage - 1)}
            >
                {__('Previous', 'stylized-anchor-link')}
            </Button>
            
            <span className="stylized-anchor-link-pagination-info">
                {currentPage} / {totalPages}
            </span>
            
            <Button 
                isSecondary
                disabled={currentPage >= totalPages}
                onClick={() => onPageChange(currentPage + 1)}
            >
                {__('Next', 'stylized-anchor-link')}
            </Button>
        </div>
    );
};

export default function Edit({ attributes, setAttributes }) {
    const { postId, postTitle, postUrl } = attributes;
    const [searchTerm, setSearchTerm] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [recentPosts, setRecentPosts] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [isSearchMode, setIsSearchMode] = useState(false);
    const postsPerPage = 5;
    const minSearchChars = 2;

    const blockProps = useBlockProps({
        className: 'dmg-read-more',
    });

    // Fetch post by ID if we have one but no title/url
    useEffect(() => {
        if (postId && (!postTitle || !postUrl)) {
            fetchPostById(postId);
        }
    }, [postId, postTitle, postUrl]);

    // Fetch recent posts on component mount
    useEffect(() => {
        fetchRecentPosts();
    }, []);

    // Effect to run search when input changes
    useEffect(() => {
        if (searchTerm.trim() && isSearchMode) {
            performSearch();
        }
    }, [page, isSearchMode]);

    // Effect to handle search term changes
    useEffect(() => {
        const trimmedSearchTerm = searchTerm.trim();
        
        if (trimmedSearchTerm.length >= minSearchChars) {
            setIsSearchMode(true);
            debouncedSearch();
        } else if (trimmedSearchTerm.length === 0 && isSearchMode) {
            // Switch back to recent posts when search is cleared
            setIsSearchMode(false);
        }
    }, [searchTerm]);

    // Create debounced search function
    const debouncedSearch = debounce(() => {
        if (searchTerm.trim().length >= minSearchChars) {
            setPage(1); // Reset to page 1 for new searches
            performSearch();
        }
    }, 500);

    const fetchRecentPosts = async () => {
        setLoading(true);
        setError('');
        
        try {
            const posts = await apiFetch({
                path: `/wp/v2/posts?per_page=5&orderby=date&order=desc&_fields=id,title,link`,
            });
            
            setRecentPosts(Array.isArray(posts) ? posts : []);
        } catch (err) {
            console.error('Failed to fetch recent posts:', err);
            setError(__('Failed to fetch recent posts.', 'stylized-anchor-link'));
        } finally {
            setLoading(false);
        }
    };

    const clearSearch = () => {
        setSearchTerm('');
        setSearchResults([]);
        setError('');
        setPage(1);
        setTotalPages(1);
        setIsSearchMode(false);
        
        // If we don't have recent posts yet, fetch them
        if (recentPosts.length === 0) {
            fetchRecentPosts();
        }
    };

    const clearSelection = () => {
        setAttributes({
            postId: null,
            postTitle: '',
            postUrl: ''
        });
    };

    const performSearch = async () => {
        if (!searchTerm.trim()) return;
        
        setLoading(true);
        setError('');
        
        // Check if the search term is a number (potential ID)
        const isNumeric = /^\d+$/.test(searchTerm.trim());
        
        if (isNumeric && page === 1) {
            // Only try direct ID lookup on page 1
            try {
                const post = await apiFetch({
                    path: `/wp/v2/posts/${searchTerm.trim()}?_fields=id,title,link`,
                });
                
                // If successful, show this post as the only result
                setSearchResults([post]);
                setTotalPages(1);
                setLoading(false);
                return;
            } catch (err) {
                // If not found by ID, continue with regular search
                console.error('Post not found by ID:', err);
            }
        }
        
        // Regular search by title
        try {
            const response = await apiFetch({
                path: `/wp/v2/posts?search=${encodeURIComponent(searchTerm)}&page=${page}&per_page=${postsPerPage}&_fields=id,title,link`,
                parse: false,
            });
            
            const posts = await response.json();
            
            // Get total pages from headers
            const totalPagesHeader = response.headers.get('X-WP-TotalPages');
            if (totalPagesHeader) {
                setTotalPages(parseInt(totalPagesHeader, 10));
            } else {
                // Fallback if headers aren't available
                setTotalPages(Math.ceil(posts.length / postsPerPage) || 1);
            }
            
            setSearchResults(Array.isArray(posts) ? posts : []);
        } catch (err) {
            console.error('Failed to search posts:', err);
            setError(__('Failed to search posts.', 'stylized-anchor-link'));
            setSearchResults([]);
        } finally {
            setLoading(false);
        }
    };

    const fetchPostById = async (id) => {
        if (!id) return;
        
        setLoading(true);
        setError('');
        
        try {
            const post = await apiFetch({
                path: `/wp/v2/posts/${id}?_fields=id,title,link`,
            });
            
            setAttributes({
                postId: post.id,
                postTitle: post.title.rendered,
                postUrl: post.link
            });
        } catch (err) {
            console.error('Post not found with that ID:', err);
            setError(__('Post not found with that ID.', 'stylized-anchor-link'));
        } finally {
            setLoading(false);
        }
    };

    const handleSelectPost = (post) => {
        setAttributes({
            postId: post.id,
            postTitle: post.title.rendered,
            postUrl: post.link
        });
    };

    const handlePageChange = (newPage) => {
        setPage(newPage);
        // The useEffect will trigger the search
    };

    // Determine which posts to display
    const displayPosts = isSearchMode ? searchResults : recentPosts;

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Post Selection', 'stylized-anchor-link')}>
                    <div className="stylized-anchor-link-search-container">
                        <div className="stylized-anchor-link-search-input-wrapper">
                            <TextControl
                                label={__('Search Posts', 'stylized-anchor-link')}
                                value={searchTerm}
                                onChange={setSearchTerm}
                                placeholder={__('Enter post title or ID...', 'stylized-anchor-link')}
                                help={__('Type at least 2 characters to search', 'stylized-anchor-link')}
                            />
                            {searchTerm && (
                                <Button 
                                    className="stylized-anchor-link-clear-search" 
                                    icon="dismiss"
                                    onClick={clearSearch}
                                    label={__('Clear search', 'stylized-anchor-link')}
                                />
                            )}
                        </div>
                    </div>
                    
                    {error && (
                        <Notice status="error" isDismissible={false}>
                            {error}
                        </Notice>
                    )}
                    
                    <PostSearchResults
                        posts={displayPosts}
                        onSelectPost={handleSelectPost}
                        loading={loading}
                        isSearchResults={isSearchMode}
                    />
                    
                    {isSearchMode && (
                        <SimplePagination
                            currentPage={page}
                            totalPages={totalPages}
                            onPageChange={handlePageChange}
                        />
                    )}
                    
                    {postId && postTitle && (
                        <div className="stylized-anchor-link-selected-post">
                            <h3 className="stylized-anchor-link-selected-title">
                                {__('Currently selected', 'stylized-anchor-link')}
                            </h3>
                            <div className="stylized-anchor-link-selected-item">
                                <span className="stylized-anchor-link-selected-text">
                                    {postTitle} <span className="post-id">#{postId}</span>
                                </span>
                                <Button 
                                    className="stylized-anchor-link-remove-selection" 
                                    icon="dismiss"
                                    onClick={clearSelection}
                                    label={__('Remove selection', 'stylized-anchor-link')}
                                />
                            </div>
                        </div>
                    )}
                </PanelBody>
            </InspectorControls>
            
            <div {...blockProps}>
                {postId && postTitle ? (
                    <p className="dmg-read-more">
                        {__('Read More: ', 'stylized-anchor-link')}
                        <a href={postUrl}>{postTitle}</a>
                    </p>
                ) : (
                    <Placeholder
                        icon="admin-links"
                        label={__('Stylized Anchor Link', 'stylized-anchor-link')}
                        instructions={__('Search for a post in the sidebar to create a stylized link.', 'stylized-anchor-link')}
                    />
                )}
            </div>
        </>
    );
}
